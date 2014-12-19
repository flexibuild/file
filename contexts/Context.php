<?php

namespace flexibuild\file\contexts;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\StringHelper;
use yii\web\UploadedFile;

use flexibuild\file\File;
use flexibuild\file\storages\StorageInterface;
use flexibuild\file\storages\Storage;
use flexibuild\file\storages\FileSystemStorage;
use flexibuild\file\formatters\FormatterInterface;

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
 * @see [[ContextFormattersTrait::setFormatters()]] for more info.
 * 
 * @property FormatterInterface|null $defaultFormatter you may use this option to set default formatter.
 * This formatter will be applied to source file.
 * Source file will not be saved, formatted by this formatter file will be saved instead of source.
 * @var mixed FormattedInterface instance of default formatter or config for creating it. Null meaning the context has not default formatter.
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
 * @see [[ContextValidatorsTrait::defaultValidators()]]
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class Context extends Component
{
    use ContextFormattersTrait;
    use ContextValidatorsTrait;

    /**
     * @event BeforeSaveEvent will be triggerred before saving file.
     */
    const EVENT_BEFORE_SAVE = 'beforeSave';
    /**
     * @event AfterSaveFile will be triggerred after saving file.
     */
    const EVENT_AFTER_SAVE = 'afterSave';

    /**
     * @var string name of this context.
     */
    public $name;

    /**
     * Class name that will be used for creating file.
     * @see [[self::createFile()]]
     * @var string the class name for creating file object.
     */
    public $fileClass = 'flexibuild\file\File';

    /**
     * Additional params that will be used for creating file.
     * @see [[self::createFile()]]
     * @var array array of additional params that will be used for creating file in key => value format.
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
     * Substring in value after the prefix gets data that can be used for manipulating with file from storage.
     * 
     */
    public $postParamStoragePrefix = '%%file-from-storage%%: ';

    /**
     * If this param is true context will auto generate all formatted versions of file on fly.
     * @var mixed may be one of the followings:
     * 
     * - true (default), means all formatted versions of file will be generated,
     * - false, means formatted versions of file will not be generated,
     * - array of formats names, only these formats will be generated.
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
        'chain' => 'flexibuild\file\formatters\ChainFormatter',
        'inline' => 'flexibuild\file\formatters\InlineFormatter',

        'image/thumb' => 'flexibuild\file\formatters\image\ThumbFormatter',
        'image/watermark' => 'flexibuild\file\formatters\image\WatermarkFormatter',
        'image/grayscale' => 'flexibuild\file\formatters\image\GrayscaleFormatter',

        'pdf/preview' => 'flexibuild\file\formatters\pdf\PdfFormatter',
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
        if (!$storage instanceof StorageInterface) {
            $storage = Yii::createObject($storage);
            if (!$storage instanceof StorageInterface) {
                throw new InvalidConfigException("Context '$this->name' has invalid storage config. It must be an instance of " . __NAMESPACE__ . '\StorageInterface or a config for creating it.');
            }
            if ($storage instanceof Storage && $storage->context === null) {
                $storage->context = $this;
            }
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
     * Parses input POST param value. The method keeps main logic of parsing POST data.
     * 
     * @param mixed $value the value must be on of the followings:
     * 
     * - an instance of File, than file data will be returned if the file exists,
     * - an instance of Uploaded file, than it object will be returned,
     * - a string, that starts with [[self::$postParamUploadPrefix]], than appropriate UploadedFile instance will be returned,
     * - a string, that starts with [[self::$postParamStoragePrefix]], than appropriate file data from storage will be returned,
     * - other value, null will be returned.
     * 
     * @return UploadedFile|string|null
     */
    public function parseInputValue($value)
    {
        if ($value instanceof UploadedFile) {
            if ($value instanceof File && $value->status !== File::STATUS_UPLOADED_FILE) {
                return $value->exists() ? $value->getData() : null;
            }
            return $value;
        }
        return is_string($value) ? $this->parseInputStringValue($value) : null;
    }

    /**
     * The method internally called in [[self::parseInputValue()]] and parse only string typed value.
     * @see [[self::parseInputValue()]]
     * @param string $value
     * @return string|null
     */
    protected function parseInputStringValue($value)
    {
        if ($value === '') {
            return null;
        }

        if (StringHelper::startsWith($value, $this->postParamUploadPrefix)) {
            $charset = Yii::$app->charset;
            $storagePrefixPos = mb_strpos($value, $this->postParamStoragePrefix, 0, $charset);
            $uploadPrefixLength = mb_strlen($this->postParamUploadPrefix, $charset);
            $valueLength = mb_strlen($value, $charset);
            $length = $storagePrefixPos === false
                ? $valueLength - $uploadPrefixLength
                : $storagePrefixPos - $uploadPrefixLength;

            $formName = mb_substr($value, $uploadPrefixLength, $length, $charset);
            if ($result = UploadedFile::getInstanceByName($formName)) {
                return $result;
            }

            if ($storagePrefixPos !== false) {
                $storagePrefixLength = mb_strlen($this->postParamStoragePrefix, $charset);
                $pos = $storagePrefixPos + $storagePrefixLength;
                $storageFileData = mb_substr($value, $pos, $valueLength - $pos, $charset);
            }
        }

        if (!isset($storageFileData) && StringHelper::startsWith($value, $this->postParamStoragePrefix)) {
            $storageFileData = StringHelper::byteSubstr($value, StringHelper::byteLength($this->postParamStoragePrefix));
        }
        if (isset($storageFileData) && $this->getStorage()->fileExists($storageFileData)) {
            return $storageFileData;
        }
        return null;
    }

    /**
     * Creates instance of File object. The method use [[$fileClass]] and [[$fileConfig]] properties for that.
     * @param array $params array of additional params that may override [[$fileClass]] and [[$fileConfig]] params.
     * @return File created File object.
     * @throws InvalidConfigException if file has incorrect format.
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

        return $result;
    }

    /**
     * Method will be called before file saving.
     * @param string $sourceFilePath full path to file that will be copied and saved.
     * @param string $originFilename origin name of file.
     * @param boolean $unlinkAfterSave whether save method must unlink file `$sourceFilePath` after saving.
     * @return boolean whether need to continue saving process.
     */
    public function beforeSaveFile(&$sourceFilePath, &$originFilename = null, &$unlinkAfterSave = false)
    {
        if ($this->getDefaultFormatter() !== null) {
            $sourceFilePath = $this->getDefaultFormatter()->format($sourceFilePath);
            $unlinkAfterSave = true;
        }

        if (!$this->hasEventHandlers(self::EVENT_BEFORE_SAVE)) {
            return true;
        }

        $event = new BeforeSaveEvent();
        $event->sender = $this;
        $event->valid = true;
        $event->sourceFilePath = $sourceFilePath;
        $event->originFilename = $originFilename;
        $event->unlinkAfterSave = $unlinkAfterSave;
        $this->trigger(self::EVENT_BEFORE_SAVE, $event);

        $sourceFilePath = $event->sourceFilePath;
        $originFilename = $event->originFilename;
        $unlinkAfterSave = $event->unlinkAfterSave;
        return $event->valid;
    }

    /**
     * Method will be called after saving file.
     * @param string $data data that can be used for manipulating with file. This value may be changed.
     * @param string $sourceFilePath path to source file that was copied.
     * @param string $originFilename origin name of file.
     */
    public function afterSaveFile(&$data, $sourceFilePath, $originFilename = null)
    {
        if (is_array($this->generateFormatsAfterSave)) {
            $data = $this->generateFormats($data, $this->generateFormatsAfterSave);
        } elseif ($this->generateFormatsAfterSave) {
            $data = $this->generateFormats($data);
        }

        if ($data === false || !$this->hasEventHandlers(self::EVENT_AFTER_SAVE)) {
            return;
        }

        $event = new AfterSaveFile;
        $event->sender = $this;
        $event->fileData = $data;
        $event->sourceFilePath = $sourceFilePath;
        $event->originFilename = $originFilename;
        $this->trigger(self::EVENT_AFTER_SAVE, $event);

        $data = $event->fileData;
    }

    /**
     * Saves new file by copying from another. Returns data that can be used for manipulating with file in the future.
     * 
     * @param string $sourceFilePath path to source file that will be copied.
     * @param string|null $originFilename name of origin file.
     * If storage allowed saving files with origin filenames it must use this param for that.
     * @return string|boolean string data that can be used for manipulating with file in the future.
     * False meaning file was not save.
     */
    public function saveFile($sourceFilePath, $originFilename = null)
    {
        $unlinkHandler = function ($filePath, $needUnlink) {
            if ($needUnlink && !@unlink($filePath)) {
                Yii::warning("Cannot unlink file: $filePath", __METHOD__);
            }
        };

        if (!$this->beforeSaveFile($sourceFilePath, $originFilename, $unlinkAfterSave)) {
            $unlinkHandler($sourceFilePath, $unlinkAfterSave);
            return false;
        }

        $storage = $this->getStorage();
        if ($storage instanceof Storage) {
            $data = $storage->saveFileByCopying($sourceFilePath, $originFilename);
        } else {
            if (false === $content = @file_get_contents($sourceFilePath)) {
                Yii::warning("Cannot load file contents: $sourceFilePath", __METHOD__);
                $data = false;
            } else {
                $data = $storage->saveFile($content, $originFilename);
            }
        }

        if ($data === false) {
            $unlinkHandler($sourceFilePath, $unlinkAfterSave);
            return false;
        }
        $this->afterSaveFile($data, $sourceFilePath, $originFilename);

        $unlinkHandler($sourceFilePath, $unlinkAfterSave);
        return $data;
    }
}
