<?php

namespace flexibuild\file\contexts;

use Yii;
use yii\base\Object;
use yii\base\Component;
use yii\validators\Validator;
use yii\validators\FileValidator;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\base\Exception;
use yii\web\UploadedFile;

use flexibuild\file\File;
use flexibuild\file\FileHandler;
use flexibuild\file\events\FileEvent;
use flexibuild\file\storages\StorageInterface;
use flexibuild\file\storages\Storage;
use flexibuild\file\storages\FileSystemStorage;
use flexibuild\file\formatters\FormatterInterface;
use flexibuild\file\formatters\Formatter;
use flexibuild\file\formatters\InlineFormatter;
use flexibuild\file\formatters\FromFormatter;
use flexibuild\file\formatters\ChainedFormatter;

/**
 * Contexts used in file manager for separating different file types (contexts).
 * 
 * For example, you may be want use ImageContext with local file system storage
 * for user avatars and PdfContext with some CDN storage. It is possible. 
 * You can configure your file manager and use the appropriate contexts with
 * the corresponding fields.
 * 
 * @property StorageInterface $storage Storage for keeping files.
 * By default will be used file system storage.
 * @see \flexibuild\file\storages\FileSystemStorage
 * 
 * @property FormatterInterface[] $formatters for processing files in format name => formatter object format.
 * By default empty array that meaning context has no formatters.
 * @see [[self::setFormatters()]] for more info.
 * 
 * 
 * @property array $validators Validators configuration that will be attached to the model that will use this context.
 * Each element may be one of:
 * - string, validator type;
 * - array with the following structure:
 * ```
 *      ['type', 'param1' => 'value1', 'param2' => 'value2']
 * ```
 * Validator type specifies the validator to be used. It can be a built-in validator name, a method name of the model class, an anonymous function, or a validator class name.
 * By default array with one element: [['yii\validators\FileValidator']],
 * that means correctly uploading (but non-required) will be validated.
 * @see [[defaultValidators()]]
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class Context extends Component implements ContextInterface
{
    /**
     * @var string name of this context.
     */
    public $name;
    //!!! check name chars in ContextsManager

    /**
     *!!!
     * @var string
     */
    public $fileClass = 'flexibuild\file\File';

    /**
     *!!!
     * @var array
     */
    public $fileConfig = [];

    /**
     * @var string prefix keeps a value of POST param value prefix.
     * POST param value of file attribute which starts with this prefix means new file was uploaded.
     * Substring in value after the prefix gets key for searching file in $_FILES array.
     */
    public $postParamUploadPrefix = '%%new-file-uploaded%%: ';

    /**
     * @var string prefix keeps a value of POST param value prefix.
     * POST param value of file attribute which starts with this prefix means existing in the storage file was selected.
     * Substring in value after the prefix gets data that can be used for loading file from storage.
     * 
     */
    public $postParamStoragePrefix = '%%file-from-storage%%: ';

    /**
     * If this param is true context will auto generate all formatted versions of file on fly.
     * @var boolean whether need to generate all formatted versions of file after saving.
     */
    public $generateFormatsAfterSave = true;

    /**
     * @var mixed
     */
    private $_storage;

    /**
     * Built in formatters aliases. Used in [[self::instantiateFormatter()]].
     * @var array in short name => formatter config format.
     */
    public static $builtInFormatters = [
        'from' => 'flexibuild\file\formatters\FromFormatter',
        'chain' => 'flexibuild\file\formatters\ChainedFormatter',
        'inline' => 'flexibuild\file\formatters\InlineFormatter',
    ];

    /**
     * Gets context's file storage object.
     * @return StorageInterface
     */
    public function getStorage()
    {
        $storage = $this->_storage;
        if ($storage === null) {
            $storage = $this->defaultStorage();
        }
        if (!is_object($storage)) {
            $storage = Yii::createObject($storage);
        }
        if ($storage instanceof Storage && $storage->context === null) {
            $storage->context = $this;
        } elseif ($storage instanceof Object && $storage->canGetProperty('context') && $storage->canSetProperty('context') && $storage->context === null) {
            $storage->context = $this;
        }
        return $this->_storage = $storage;
    }

    /**
     * Sets context's file storage. You can use Yii config standarts.
     * @param mixed $storage storage object or storage configuration.
     */
    public function setStorage($storage)
    {
        $this->_storage = $storage;
    }

    /**
     * Returns default context's storage configuration. This method may be
     * overrided in subclasses.
     * @return mixed default storage config. Used in `getStorage()` if storage
     * was not set.
     */
    protected function defaultStorage()
    {
        return FileSystemStorage::className();
    }

    /**
     * @var array
     */
    private $_formatters = [];

    /**
     * Gets context's formatters.
     * @return FormatterInterface[]
     */
    public function getFormatters()
    {
        $result = [];
        foreach ($this->formatterNames() as $name) {
            $result[$name] = $this->getFormatter($name);
        }
        return $result;
    }

    /**
     * Sets context's file formatters. You can use Yii config standarts.
     * @param array $formatters array each element of which is formatter object or formatter config.
     * 
     * You may use some helpful syntax for defining formatters. Examples:
     * ```php
     *  [
     *      'format_name_1' => 'YOUR\FORMATTER\CLASS\NAME',
     *      'format_name_2' => 'built-in-name',
     *      'format_name_3' => [
     *          'YOUR\FORMATTER\CLASS\NAME',
     *          'param1' => 'value1',
     *          'param2' => 'value2',
     *      ],
     *      'format_name_4' => [
     *          'class' => 'YOUR\FORMATTER\CLASS\NAME',
     *          'param1' => 'value1',
     *          'param2' => 'value2',
     *      ],
     *      'format_name_5' => ['built-in-name', 'param1' => 'value1', 'param2' => 'value2'],
     *      'format_name_6' => function ($readFilePath, $formatter) {
     *          // your logic, where
     *          // - $readFilePath: string, path to file, that must be formatted,
     *          // - $formatter: InlineFormatter, instance of self formatter object.
     *      },
     * 
     *      // built-in concrete examples
     *      'format_name_7' => ['from', 'from' => 'format_name_x', 'formatter' => [
     *          // formatter configuration in a similar way, for example:
     *          'YOUR\FORMATTER\CLASS\NAME',
     *      ]]
     *      'format_name_8' => ['chain', 'formatters' => [
     *          // formatters array, each element is a configuration in a similar way, for example:
     *          'YOUR\FORMATTER\CLASS\NAME1',
     *          'YOUR\FORMATTER\CLASS\NAME2',
     *      ]],
     *  ]
     * ```
     */
    public function setFormatters($formatters)
    {
        foreach ($formatters as $name => $formatter) {
            if (!preg_match('/^[0-9a-z\_]+$/i', $name)) {
                throw new InvalidConfigException("Incorrect name '$name', the name of formatter may have digits, letters or underscope only.");
            }
        }
        $this->_formatters = $formatters;
    }

    /**
     * Checks existing of formatter by name.
     * @param string $name
     * @return bool whether formatter with `$name` exists.
     */
    public function hasFormatter($name)
    {
        return isset($this->_formatters[$name]);
    }

    /**
     * Returns formatter object by name.
     * @param string $name
     * @return FormatterInterface the formatter with name === `$name`.
     * @throws InvalidParamException if formatter with the name is not defined.
     */
    public function getFormatter($name)
    {
        if (!isset($this->_formatters[$name])) {
            throw new InvalidParamException("Cannot find formatter with name '$name'.");
        }

        $formatter = $this->_formatters[$name];
        return $this->_formatters[$name] = $this->instantiateFormatter($formatter, $name);
    }

    /**
     * Creates formatter by config.
     * @param mixed $formatter an instance of [[FormatterInterface]] or config for creating it.
     * @param string|null $name the name of formatter. Null meaning the formatter has not a name (by default).
     * @return FormatterInterface created formatter.
     */
    public function instantiateFormatter($formatter, $name = null)
    {
        if (is_object($formatter)) {
            return $formatter;
        }

        if ($formatter instanceof \Closure) {
            $formatter = ['inline', 'format' => $formatter];
        } elseif (is_string($formatter)) {
            $formatter = [$formatter];
        }
        if (is_array($formatter) && isset($formatter[0])) {
            if (isset(static::$builtInFormatters[$formatter[0]])) {
                $className = static::$builtInFormatters[$formatter[0]];
            } else {
                $className = $formatter[0];
            }
            unset($formatter[0]);
            $formatter = array_merge(['class' => $className], $formatter);
        }

        $formatter = Yii::createObject($formatter);
        if ($formatter instanceof Formatter) {
            if ($formatter->context === null) {
                $formatter->context = $this;
            }
            if ($name !== null && $formatter->name === null) {
                $formatter->name = $name;
            }
        } elseif ($formatter instanceof Object) {
            if ($formatter->canGetProperty('context') && $formatter->canSetProperty('context') && $formatter->context === null) {
                $formatter->context = $this;
            }
            if ($name !== null && $formatter->canGetProperty('name') && $formatter->canSetProperty('name') && $formatter->name === null) {
                $formatter->name = $name;
            }
        }
    }

    /**
     * @return array list all formatters names.
     */
    public function formatterNames()
    {
        return array_keys($this->_formatters);
    }

    /**
     * @var array
     */
    private $_validators;

    /**
     * Validators configuration that will be attached to the model that will use this context.
     * 
     * Each element will be an array with the following structure:
     * ```
     *      ['type', 'param1' => 'value1', 'param2' => 'value2']
     * ```
     * 
     * Validator type specifies the validator to be used.
     * It can be a built-in validator name, a method name of the model class,
     * an anonymous function, or a validator class name.
     * 
     * By default array with one element: [['yii\validators\FileValidator']],
     * that means correctly uploading (but non-required) will be validated.
     * @see [[defaultValidators()]]
     * 
     * @return array array of context's files validators.
     */
    public function getValidators()
    {
        if ($this->_validators === null) {
            $this->setValidators($this->defaultValidators());
        }
        return $this->_validators;
    }

    /**
     * Sets validators configuration.
     * @param array $validators validators configuration. Each element may be one of:
     * - string, validator type;
     * - array with the following structure:
     * ```
     *      ['type', 'param1' => 'value1', 'param2' => 'value2']
     * ```
     * Validator type specifies the validator to be used.
     * It can be a built-in validator name, a method name of the model class,
     * an anonymous function, or a validator class name.
     * 
     * @throws InvalidConfigException if config has incorrect format.
     */
    public function setValidators($validators)
    {
        if (!is_array($validators)) {
            throw new InvalidConfigException('Validators param must be an array.');
        }

        foreach ($validators as $ind => $validator) {
            if (!is_array($validator)) {
                $validators[$ind] = $validator = [$validator];
            } elseif (!isset($validator[0])) {
                throw new InvalidConfigException("Each context's validator must have validator type as element with index 0 in parameters array.");
            }

            $type = $validator[0];
            if (!is_string($type) && !($type instanceof \Closure)) {
                throw new InvalidConfigException('Incorrect type of validator: '.gettype($type).'.');
            }
        }

        $this->_validators = $validators;
    }

    /**
     * Returns default context's validators configuration. This method may be
     * overrided in subclasses.
     * @return array default config for [[getValidators()]].
     * This value will be used in [[getValidators()]] if 'validators' property was not set.
     */
    protected function defaultValidators()
    {
        return [FileValidator::className()];
    }

    /**
     * !!!
     * @param array $params
     * @return File
     * @throws InvalidConfigException
     */
    public function createFile($params = [])
    {
        $params = array_merge([
            'class' => $this->fileClass,
        ], $this->fileConfig, $params);
        if (!isset($params['context'])) {
            $params['context'] = $this;
        }

        $result = Yii::createObject($params);
        if (!$result instanceof File) {
            throw new InvalidConfigException('Invalid configuration for file in '.__METHOD__.'.');
        }

        $result->handler->on(FileHandler::EVENT_AFTER_SAVE, [$result, 'afterSaveFile']);

        return $result;
    }

    /**
     * Event 'afterSave' handler. Main logic is check `$generateFormatsAfterSave` param and generate all formats if need.
     * @param FileEvent $event
     */
    public function afterSaveFile($event)
    {
        if ($this->generateFormatsAfterSave) {
            $this->generateAllFormats($event->file);
        }
    }

    /**
     * !!!
     * @param File $file
     * @param boolean $regenerate
     */
    public function generateAllFormats($file, $regenerate = false)
    {
        $file->generateFormats($this->formatterNames(), $regenerate);
    }

    /**
     * !!!
     * Parses input POST param value.
     * @param mixed $value
     * @return UploadedFile|string|null
     */
    public function parseInputValue($value)
    {
        if ($value instanceof FileHandler) {
            $value = $value->file;
        }
        if ($value instanceof UploadedFile) {
            if ($value instanceof File && $value->status !== File::STATUS_UPLOADED_FILE) {
                return $value->exists() ? $value->getData() : null;
            }
            return $value;
        }
        return is_string($value) ? $this->parseInputStringValue($value) : null;
    }

    /**
     * !!!
     * @param string $value
     * @return UploadedFile|string|null
     */
    protected function parseInputStringValue($value)
    {
        if ($value === '') {
            return null;
        }
        if (StringHelper::startsWith($value, $this->postParamUploadPrefix)) {
            $formName = StringHelper::byteSubstr($value, StringHelper::byteLength($this->postParamUploadPrefix));
            return UploadedFile::getInstanceByName($formName) ?: null;
        } elseif (StringHelper::startsWith($value, $this->postParamStoragePrefix)) {
            $storageFileData = StringHelper::byteSubstr($value, StringHelper::byteLength($this->postParamStoragePrefix));
            if ($this->getStorage()->fileExists($storageFileData)) {
                return $storageFileData;
            }
        }
        return null;
    }
}
