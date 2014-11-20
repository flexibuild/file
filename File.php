<?php

namespace flexibuild\file;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\InvalidCallException;
use yii\base\InvalidParamException;
use yii\web\UploadedFile;
use yii\base\ErrorHandler;
use yii\helpers\Url;

use flexibuild\file\helpers\FileSystemHelper;
use flexibuild\file\storages\Storage;
use flexibuild\file\contexts\Context;
use flexibuild\file\formatters\FromFormatter;
use flexibuild\file\events\CannotGetUrlEvent;
use flexibuild\file\events\CannotGetUrlHandlers;
use flexibuild\file\events\DataChangedEvent;

/**
 * Base file object class which collects basic properties and methods for files.
 * 
 * This class has a bridge to [[FileHandler]] class, and they are associated as one-to-one.
 * It is because this class extends [[\yii\web\UploadedFile]] that extends [[\yii\base\Object]] and does not implement events logic.
 * On other hand we have to use extended from [[\yii\web\UploadedFile]] class for capability with standart yii file validators.
 * 
 * This class changes structure of [[yii\web\UploadedFile]]. All properties and methods
 * will be accessed through getters and setters.
 * @see [[_changeStructure()]]
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 * 
 * @property string $data !!!
 * 
 * @property-read string|null $defaultUrl !!!
 * @property-read string $url url to source file !!!
 * @property-read FileHandler $handler !!!
 * @property-read boolean $isEmpty whether file is empty or not.
 * @property-read array $formatList names of exists formatted versions for this file.
 * 
 * The class also gives short properties for urls of each format, 
 * for example: `$file->asSmall` is alias to `$file->getUrl('small')`.
 */
class File extends UploadedFile
{
    /**
     * Const that keeps value for status which means the file is empty.
     */
    const STATUS_EMPTY_FILE = 'empty';
    /**
     * Const that keeps value for status which means the file was uploaded through web request.
     */
    const STATUS_UPLOADED_FILE = 'uploaded';
    /**
     * Const that keeps value for status which means the file was initialized from existing data.
     */
    const STATUS_INITIALIZED_FILE = 'initialized';

    /**
     * @var string status value which may be on of:
     * 
     * - 'empty' or [[self::STATUS_EMPTY_FILE]], which means the file is empty.
     * - 'uploaded' or [[self::STATUS_UPLOADED_FILE]], which means the file was uploaded through web request (by default for back capability).
     * - 'initialized' or [[self::STATUS_INITIALIZED_FILE]], which means the file was initialized from existing data.
     */
    public $status = self::STATUS_UPLOADED_FILE;

    /**
     * @var Context !!!
     */
    public $context;

    /**
     * @var ModelBehavior|null the owner that creates this object.
     */
    public $owner;

    /**
     * @var string|null the name of attribute that keeps data of current file in the `$owner`.
     */
    public $dataAttribute;

    /**
     * @var mixed urls that will used by default when calling [[getUrl()]] of empty (status === 'empty') file:
     * - `null` (default): file has no default url, exception will be thrown on [[getUrl()]].
     * - string: default url, that will be returned.
     * - array: array of default urls detalized by formats.
     * Value with index === 0 will be used as default url, i.e. if format does not specified or element with key === `$format` was not found.
     * You also can use Yii aliases in values of this param, because calculated value will be passed in [[\yii\helpers\Url::to()]] method.
     * 
     * Example:
     *  ```
     *      [
     *          '@web/images/no-photo.png', // url that used by default (if format does not specified or element with key === `$format` was not found)
     *          'small' => '@web/images/no-photo_30x30.png', // default url for 'small' format,
     *      ]
     *  ```
     * 
     */
    public $defaultUrls;

    /**!!!
     * @var string
     */
    public $handlerClass = 'flexibuild\file\FileHandler';

    /**
     *!!!
     * @var array
     */
    public $handlerConfig = [];

    /**
     * Event handlers that will be attached to [[FileHandler]] object.
     * @see FileHandler
     * @var array of events handler. Each element must be an array that has two or three elements:
     * - the first (index 0): event name
     * - the second (index 1): event handler
     * - the third (index 2, non-required): data that will be attached to handler
     * 
     * For example:
     * ```
     *      'events' => [
     *          ['cannotGetUrl', 'flexibuild\file\events\CannotGetUrlHandlers::formatFileOnFly'],
     *          ['cannotGetUrl', 'flexibuild\file\events\CannotGetUrlHandlers::returnDefaultUrl'],
     *          ['cannotGetUrl', 'flexibuild\file\events\CannotGetUrlHandlers::returnAboutBlank'],
     *          ...
     *      ],
     * ```
     */
    public $events = [];

    /**
     * @var string|null the format that will be used in magic [[__toString()]] method by default.
     */
    public $toStringFormat;

    /**
     * @var mixed the scheme value that will be used in magic [[__toString()]] method by default.
     */
    public $toStringScheme;

    /**
     * @var FileHandler
     */
    private $_handler;

    /**
     * @var string data that can be used for loading file from context's storage.
     */
    private $_data;

    /**!!!
     * @var string|null
     */
    private $_name;

    /**!!!
     * @var string|null
     */
    private $_tempName;

    /**!!!
     * @var string|null
     */
    private $_type;

    /**!!!
     * @var integer|null
     */
    private $_size;

    /**!!!
     * @var integer|null
     */
    private $_error;

    /**
     * Changes structure of base class.
     * @see [[_changeStructure()]]
     * 
     * Checks required property `$context`.
     * Attaches event handlers from `$events` property.
     * @throws InvalidConfigException if required property `$context` is missed or is incorrect.
     */
    public function init()
    {
        $this->_handler = Yii::createObject(array_merge([
            'class' => $this->handlerClass,
        ], $this->handlerConfig, [
            'file' => $this,
        ]));
        $this->_changeStructure();

        parent::init();

        if (!$this->context instanceof Context) {
            throw new InvalidConfigException('An instance of '.Context::className().' is required for '.get_class($this).'.');
        }

        foreach ($this->events as $event) {
            if (!isset($event[0], $event[1])) {
                throw new InvalidConfigException('Incorrect config of '.get_class($this).'::$events param.');
            }
            $this->_handler->on($event[0], $event[1], isset($event[2]) ? $event[2] : null);
        }
    }

    /**
     * Changes structure of base class.
     * It will unset all simple properties.
     * They will be accessed through getters and setter.
     */
    private function _changeStructure()
    {
        $name = $this->name;
        $tempName = $this->tempName;
        $type = $this->type;
        $size = $this->size;
        $error = $this->error;

        unset($this->name);
        unset($this->tempName);
        unset($this->type);
        unset($this->size);
        unset($this->error);

        $this->setName($name);
        $this->setTempName($tempName);
        $this->setType($type);
        $this->setSize($size);
        $this->setError($error);
    }

    /**
     * Parses name of formatter from short aliases like `as{Format}`.
     * @param string $name the name of aliases that must be parsed.
     * @return string|boolean string name of formatter. False meaning the `$name` does not match existing formatter name.
     */
    private function _parseFormatAlias($name)
    {
        if (!preg_match('/^as([\w_]+)$/', $name, $matches)) {
            return false;
        }
        $format = lcfirst($matches[1]);
        return $this->context->hasFormatter($format) ? $format : false;
    }

    /**
     * @inheritdoc
     * Returns true for `as{Format}` properties too.
     */
    public function canGetProperty($name, $checkVars = true)
    {
        if ($this->_parseFormatAlias($name) !== false) {
            return true;
        }
        return parent::canGetProperty($name, $checkVars);
    }

    /**
     * @inheridoc
     * Returns url for formatted version of files for properties like `as{Format}`.
     */
    public function __get($name)
    {
        if (false !== $format = $this->_parseFormatAlias($name)) {
            return $this->getUrl($format);
        }
        return parent::__get($name);
    }

    /**
     * @inheritdoc
     * @throws InvalidCallException when trying to set `as{Format}` property.
     */
    public function __set($name, $value)
    {
        if ($this->_parseFormatAlias($name) !== false) {
            throw new InvalidCallException('Setting read-only property: ' . get_class($this) . "::$name.");
        }
        parent::__set($name, $value);
    }

    /**
     * @inheritdoc
     * Returns true for properties like `as{Format}` if file has url for this format.
     */
    public function __isset($name)
    {
        if (false !== $format = $this->_parseFormatAlias($name)) {
            try {
                return $this->getUrl($format) !== null;
            } catch (\Exception $ex) {
                return false;
            }
        }
        return parent::__isset($name);
    }

    /**
     * @inheritdoc
     * @throws InvalidCallException when trying to unset `as{Format}` property.
     */
    public function __unset($name, $value)
    {
        if ($this->_parseFormatAlias($name) !== false) {
            throw new InvalidCallException('Unsetting read-only property: ' . get_class($this) . "::$name.");
        }
        parent::__set($name, $value);
    }

    // !!!
    public function getHandler()
    {
        return $this->_handler;
    }

    /**
     * !!!
     * @return type
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * !!!
     * @param type $data
     */
    public function setData($data)
    {
        $oldData = $this->_data;
        $this->_data = $data;

        if ($oldData !== $data) {
            $this->getHandler()->triggerDataChangedEvent($oldData, $data);
        }
    }

    /**
     * Clears the file (sets all fields to empty).
     * Sets the file status as [[self::STATUS_EMPTY_FILE]] too.
     * @param File $file
     */
    public function clearFile()
    {
        $this->resetCalculatedProperties();
        $this->status = self::STATUS_EMPTY_FILE;
        $this->setData(null);
    }

    /**
     * Populates the file with data.
     * Sets the file status as [[self::STATUS_INITIALIZED_FILE]] too.
     * @param string $data
     */
    public function populateFileData($data)
    {
        $this->resetCalculatedProperties();
        $this->status = self::STATUS_INITIALIZED_FILE;
        $this->setData($data);
    }

    /**
     * Populates the file with data from UploadedFile.
     * Sets the file status as [[self::STATUS_UPLOADED_FILE]] too.
     * @param UploadedFile $uploadedFile
     * @throws InvalidParamException if incorrect `$uploadedFile` was passed.
     */
    public function populateFromUploadedFile($uploadedFile)
    {
        if (!$uploadedFile instanceof UploadedFile) {
            throw new InvalidParamException('An instance of '.UploadedFile::className().' is required in '.__METHOD__.'.');
        }
        $properties = [
            'name' => $uploadedFile->name,
            'tempName' => $uploadedFile->tempName,
            'type' => $uploadedFile->type,
            'size' => $uploadedFile->size,
            'error' => $uploadedFile->error,
        ];

        $this->resetCalculatedProperties();
        Yii::configure($this, $properties);
        $this->status = self::STATUS_UPLOADED_FILE;
        $this->setData(null);
    }

    /**
     * !!!
     * @return type
     */
    public function getName()
    {
        if ($this->_name !== null) {
            return $this->_name;
        }
        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            $storage = $this->context->getStorage();
            if ($storage instanceof Storage) {
                return $this->_name = $storage->getBaseName($this->getData());
            } else {
                return $this->_name = FileSystemHelper::basename($this->getUrl());
            }
        }
        return null;
    }

    /**
     * !!!
     */
    public function setName($name)
    {
        $this->_name = $name;
    }

    /**
     * !!!
     */
    public function getTempName()
    {
        if ($this->_tempName !== null) {
            return $this->_tempName;
        }
        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            return $this->_tempName = $this->context->getStorage()->getReadFilePath($this->getData());
        }
        return null;
    }

    /**
     * !!!
     */
    public function setTempName($tempName)
    {
        $this->_tempName = $tempName;
    }

    /**
     * !!!
     */
    public function getType()
    {
        if ($this->_type !== null) {
            return $this->_type;
        }
        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            $storage = $this->context->getStorage();
            if ($storage instanceof Storage) {
                return $this->_type = $storage->getMimeType($this->getData());
            } else {
                $tempName = $this->getTempName();
                return $this->_type = FileSystemHelper::getMimeType($tempName);
            }
        }
        return null;
    }

    /**
     * !!!
     */
    public function setType($type)
    {
        $this->_type = $type;
    }

    /**
     * !!!
     */
    public function getSize()
    {
        if ($this->_size !== null) {
            return $this->_size;
        }
        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            $storage = $this->context->getStorage();
            if ($storage instanceof Storage) {
                return $this->_size = $storage->getFileSize($this->getData());
            } else {
                $tempName = $this->getTempName();
                return $this->_size = filesize($tempName);
            }
        }
        return null;
    }

    /**
     * !!!
     */
    public function setSize($size)
    {
        $this->_size = $size;
    }

    /**
     * !!!
     */
    public function getError()
    {
        if ($this->_error !== null) {
            return $this->_error;
        }
        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            return $this->_error = UPLOAD_ERR_OK;
        } elseif ($this->status === self::STATUS_EMPTY_FILE) {
            return $this->_error = UPLOAD_ERR_NO_FILE;
        }
        return null;
    }

    /**
     * !!!
     */
    public function setError($error)
    {
        $this->_error = $error;
    }

    /**
     * Returns default url for current `$format` with current `$scheme`.
     * @param string|null $format a name of format. Null meaning url for the source file must be returned.
     * @param boolean|string $scheme the URI scheme to use in the generated URL:
     *
     * - `false` (default): generating a relative URL.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::hostInfo]].
     * - string: generating an absolute URL with the specified scheme (either `http` or `https`).
     * @return string|null the url to source or formatted file according to `$format` param.
     * Null meaning default url was not defined.
     */
    public function getDefaultUrl($format = null, $scheme = null)
    {
        if (is_array($this->defaultUrls)) {
            if ($format !== null && array_key_exists($format, $this->defaultUrls)) {
                $url = $this->defaultUrls[$format];
            } elseif (isset($this->defaultUrls[0])) {
                $url = $this->defaultUrls[0];
            }
        } else {
            $url = $this->defaultUrls;
        }

        if (isset($url)) {
            return Url::to($url, $scheme);
        }
        return null;
    }

    /**
     * Returns file url for accessing to file through http protocol.
     * 
     * This method triggers [[CannotGetUrlEvent]] when cannot get url.
     * @see CannotGetUrlEvent
     * 
     * @param string|null $format a name of format. Null meaning url for the source file must be returned.
     * @param boolean|string $scheme the URI scheme to use in the generated URL:
     *
     * - `false` (default): generating a relative URL.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::hostInfo]].
     * - string: generating an absolute URL with the specified scheme (either `http` or `https`).
     * 
     * @return string the url to source or formatted file according to `$format` param.
     */
    public function getUrl($format = null, $scheme = null)
    {
        if ($this->status === self::STATUS_EMPTY_FILE) {
            if (null !== $result = $this->getDefaultUrl($format, $scheme)) {
                return $result;
            } else {
                $case = CannotGetUrlEvent::CASE_EMPTY_FILE;
            }
        } elseif ($this->status === self::STATUS_UPLOADED_FILE) {
            $case = CannotGetUrlEvent::CASE_FILE_JUST_UPLOADED;
        } elseif (!$this->exists($format)) {
            if ($format === null || !$this->exists()) {
                $case = CannotGetUrlEvent::CASE_FILE_NOT_FOUND;
            } else {
                $case = CannotGetUrlEvent::CASE_FORMAT_NOT_FOUND;
            }
        } else {
            try {
                return $this->context->getStorage()->getUrl($this->getData(), $format, $scheme);
            } catch (\Exception $ex) {
                $case = CannotGetUrlEvent::CASE_EXCEPTION_THROWED;
                $exception = $ex;
            }
        }

        return $this->getHandler()->triggerCannotGetUrl($case, $format, $scheme, isset($exception) ? $exception : null);
    }

    /**
     * !!!
     * @param string $format
     * @param boolean $regenerate
     * @throws InvalidParamException
     * @throws InvalidCallException
     */
    public function generateFormat($format, $regenerate = false)
    {
        list($formattedTmpFile, $canUnlink) = $this->generateFormatInternal($format, $regenerate);
        if ($canUnlink && !@unlink($formattedTmpFile)) {
            Yii::warning("Cannot remove temporary formatted file '$formattedTmpFile'.", __METHOD__);
        }
    }

    /**
     * !!!
     * @param array $formats
     * @param boolean $regenerate
     * 
     * @throws InvalidParamException
     * @throws InvalidCallException
     * @throws InvalidConfigException if `$formats` hierarchy has cycles.
     */
    public function generateFormats($formats, $regenerate = false)
    {
        if (!count($formats)) {
            return;
        }

        $formats = array_unique($formats);
        $formats = array_combine($formats, $formats);
        $preparedFormats = [];

        while (count($formats)) {
            $formattersToGenerate = [];
            $firstBadFormatter = null;
            foreach ($formats as $format) {
                $formatter = $this->context->getFormatter($format);
                if (!$formatter instanceof FromFormatter) {
                    $formattersToGenerate[] = $format;
                } elseif (isset($preparedFormats[$formatter->from]) || !isset($formats[$formatter->from])) {
                    $formattersToGenerate[] = $format;
                } elseif ($firstBadFormatter === null) {
                    $firstBadFormatter = $formatter;
                }
            }

            if (!count($formattersToGenerate)) {
                /* @var $firstBadFormatter FromFormatter */ // will isset here
                throw new InvalidConfigException("Formatter '$firstBadFormatter->name' of '{$this->context->name}' context has a cycle in own formatters hierarchy.");
            }

            foreach ($formattersToGenerate as $format) {
                $formatter = $this->context->getFormatter($format);
                if (!$formatter instanceof FromFormatter) {
                    $preparedFormats[$format] = $this->generateFormatInternal($format, $regenerate);
                } else {
                    if (!isset($preparedFormats[$formatter->from])) {
                        $preparedFormats[$formatter->from] = $this->generateFormatInternal($formatter->from, false);
                    }
                    $preparedFormats[$format] = $this->generateFormatInternal($format, $regenerate, $preparedFormats[$formatter->from][0]);
                }
                unset($formats[$format]);
            }
        }

        foreach ($preparedFormats as $format => $tmpFileData) {
            list($tmpFileName, $canUnlink) = $tmpFileData;
            if ($canUnlink && !@unlink($tmpFileName)) {
                Yii::warning("Cannot remove temporary formatted file '$tmpFileName'.", __METHOD__);
            }
        }
    }

    /**
     * @internal internally used in [[self::generateFormat()]] & [[self::generateFormats()]].
     * Generates formatted version of file.
     * 
     * @param string $format the name of format.
     * @param boolean $regenerate whether formatted file must be generated even if it exists.
     * @param string|null $tmpNameDependentFile path to tmp filename of dependent formatted version.
     * It's used only for [[FromFormatter]]. 'Dependent' means the temp filename of [[FromFormatter::$from]] file.
     * 
     * @return array array that contains 2 elements:
     * 
     * - string (index 0), temp file path to the new formatted version of file.
     * - boolean (index 1), whether temp file can be unlinked.
     * !!!
     * @throws InvalidParamException
     * @throws InvalidCallException
     */
    protected function generateFormatInternal($format, $regenerate = false, $tmpNameDependentFile = null)
    {
        if ($this->status !== self::STATUS_INITIALIZED_FILE) {
            if ($this->status === self::STATUS_UPLOADED_FILE) {
                throw new InvalidCallException('Cannot generate format for just uploaded file.');
            } else {
                throw new InvalidCallException('Cannot generate format for empty file attribute.');
            }
        }

        $data = $this->getData();
        if (!$regenerate && $this->exists($format)) {
            return [$this->getTempName(), false];
        }

        $storage = $this->context->getStorage();
        $formatter = $this->context->getFormatter($format);
        if ($formatter instanceof FromFormatter) {
            if ($tmpNameDependentFile === null) {
                $this->generateFormat($formatter->from, false);
                $tmpNameDependentFile = $this->context->getStorage()->getReadFilePath($this->getData(), $formatter->from);
            }
            $formattedTmpFile = $formatter->format($tmpNameDependentFile, true);
        } else {
            $readFilePath = $this->getTempName();
            $formattedTmpFile = $formatter->format($readFilePath);
        }

        if ($storage instanceof Storage) {
            $resultData = $storage->saveFormattedFileByCopying($data, $formattedTmpFile, $format);
        } else {
            if (false === $content = @file_get_contents($formattedTmpFile)) {
                Yii::warning("Cannot read content of the file '$formattedTmpFile'.", __METHOD__);
                $resultData = false;
            } else {
                $resultData = $storage->saveFormattedFile($data, $content, $format);
            }
        }

        if ($resultData === false) {
            throw new InvalidConfigException("Cannot save formatted as '$format' file for '$this->name' in the '{$this->context->name}' context.");
        }
        $this->setData($resultData);
        return [$formattedTmpFile, true];
    }

    /**
     * PHP magic method that returns the string representation of this object.
     * @see [[$toStringFormat]]
     * @see [[$toStringScheme]]
     * @return string the url of this file.
     */
    public function __toString()
    {
        // __toString cannot throw exception
        // use trigger_error to bypass this limitation
        try {
            return $this->getUrl($this->toStringFormat, $this->toStringScheme);
        } catch (\Exception $e) {
            ErrorHandler::convertExceptionToError($e);
            return '';
        }
    }

    /**
     * !!!
     */
    protected function resetCalculatedProperties()
    {
        $this->_name = null;
        $this->_tempName = null;
        $this->_type = null;
        $this->_size = null;
        $this->_error = null;
    }

    /**
     * You cannot use this method since this class. Use [[save()]] method.
     * @see [[save()]]]
     * @throws InvalidCallException
     */
    public function saveAs($file, $deleteTempFile = true)
    {
        throw new InvalidCallException('Method '.__METHOD__.' does not allowed since class '.__CLASS__.'.');
    }

    /**
     * Saves file if it has not been saved.
     * 
     * This method triggers 'beforeSave' and 'afterSave' events.
     * You can set [[FileEvent::$valid]] property as false in 'beforeSave' event handler for stopping saving process.
     * 
     * @param boolean $deleteTempFile  whether to delete the temporary file after saving.
     * If true, you will not be able to save the uploaded file again in the current request.
     * @return boolean whether file has been successfully saved.
     */
    public function save($deleteTempFile = true)
    {
        if (!$this->getHandler()->triggerBeforeSave()) {
            return false;
        }

        if ($this->getError() != UPLOAD_ERR_OK) {
            return false;
        }
        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            return false;
        }

        $storage = $this->context->getStorage();
        $tempFile = $this->getTempName();
        if ($storage instanceof Storage) {
            $data = $storage->saveFileByCopying($tempFile, $this->getName());
        } else {
            if (false === $content = @file_get_contents($tempFile)) {
                $data = false;
            } else {
                $data = $storage->saveFile($content, $this->getName());
            }
        }

        if ($data === false) {
            return false;
        }

        if ($deleteTempFile && !@unlink($tempFile)) {
            Yii::warning("Cannot remove temporary uploaded file '$tempFile'.", __METHOD__);
        }
        $this->populateFileData($data);
        $this->getHandler()->triggerAfterSave();
        return true;
    }

    /**
     * Deletes file.
     * 
     * This method triggers 'beforeDelete' and 'afterDelete' events.
     * You can set [[FileEvent::$valid]] property as false in 'beforeDelete' event handler for stopping deleting process.
     * 
     * @return boolean whether file has been successfully deleted.
     */
    public function delete()
    {
        if (!$this->getHandler()->triggerBeforeDelete()) {
            return false;
        }

        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            $storage = $this->context->getStorage();
            if ($storage instanceof Storage) {
                $result = $storage->deleteFile($this->getData());
            } else {
                $result = false;
            }
        } elseif ($this->status = self::STATUS_UPLOADED_FILE) {
            $result = @unlink($this->getTempName());
        } else {
            $result = false;
        }

        if ($result) {
            $this->clearFile();
            $this->handler->triggerAfterDelete();
        }
        return $result;
    }

    /**
     * Note! This method returns filename (like in `pathinfo(..., PATHINFO_FILENAME)), NOT basename (like `basename()`).
     * It is used because the same logic was in [[\yii\web\UploadedFile]] (for back capability).
     * 
     * @inheritdoc
     */
    public function getBaseName()
    {
        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            $storage = $this->context->getStorage();
            if ($storage instanceof Storage) {
                return $storage->getFileName($this->getData());
            }
        }
        return FileSystemHelper::filename($this->getName());
    }

    /**
     * @inheritdoc
     */
    public function getExtension()
    {
        if ($this->status === FileHandler::STATUS_INITIALIZED_FILE) {
            $storage = $this->context->getStorage();
            if ($storage instanceof Storage) {
                return $storage->getExtension($this->getData());
            }
        }
        return FileSystemHelper::extension($this->getName());
    }

    /**
     * Check file is empty or not. Empty file may be:
     * - if this is uploaded file and error == `UPLOAD_ERR_NO_FILE'
     * - if this is empty file (status === STATUS_EMPTY_FILE)
     * @return boolean whether file is empty.
     */
    public function getIsEmpty()
    {
        return $this->getError() == UPLOAD_ERR_NO_FILE;
    }

    /**
     * Returns value that indicates whether file is exists.
     * @param string $format checks formatted version of file if this parameter is passed.
     * @return boolean whether file (and formatted version) exists.
     */
    public function exists($format = null)
    {
        return !$this->getIsEmpty() && $this->context->getStorage()->fileExists($this->getData(), $format);
    }

    /**
     * Returns array names of exists formatted versions.
     * @return array available formats names for the file.
     */
    public function getFormatList()
    {
        return $this->context->getStorage()->getFormatList($this->getData());
    }
}
