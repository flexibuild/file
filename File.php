<?php

namespace flexibuild\file;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\InvalidCallException;
use yii\base\InvalidParamException;
use yii\web\UploadedFile;
use yii\base\ErrorHandler;
use yii\helpers\Url;
use yii\helpers\Html;

use flexibuild\file\helpers\FileSystemHelper;
use flexibuild\file\storages\Storage;
use flexibuild\file\contexts\Context;

use flexibuild\file\events\CannotGetUrlEvent;
use flexibuild\file\events\CannotGetUrlHandlers;
use flexibuild\file\events\DataChangedEvent;
use flexibuild\file\events\FileEvent;
use flexibuild\file\events\FileValidEvent;
use flexibuild\file\events\BeforeDeleteEvent;
use flexibuild\file\events\AfterDeleteEvent;

/**
 * Base file object class which collects basic properties and methods for files.
 * 
 * This class changes structure of [[yii\web\UploadedFile]]. All properties and methods
 * will be accessed through getters and setters.
 * @see [[_changeStructure()]]
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 * 
 * @property string|null $data string file data that can be used for manipulating with file using in context's storage.
 * @property string|null $name basename of the file. Null meaning file is empty.
 * @property string|null $tempName temp filepath for uploaded file, and read file path fot initialized file. Null meaning file is empty.
 * @property string|null $type string|null mime type of file. Null meaning file is empty.
 * @property integer|null $size file size in bytes. Null meaning file is empty.
 * 
 * @property integer|null $error file error. It will be:
 * - integer|null one of UPLOAD_ERR_* constants values for uploaded file,
 * - UPLOAD_ERR_OK for initialized file,
 * - UPLOAD_ERR_NO_FILE for empty file.
 * 
 * 
 * @property-read string $url url to source file (@see [[self::getUrl()]]).
 * @property-read boolean $isEmpty whether file is empty or not.
 * @property-read array $formatList names of exists formatted versions for this file.
 * 
 * The class also gives short properties for urls of each format, 
 * for example: `$file->asSmall` is alias to `$file->getUrl('small')`.
 */
class File extends FileComponent
{
    /**
     * @event CannotGetUrlEvent an event raised when cannot get url.
     */
    const EVENT_CANNOT_GET_URL = 'cannotGetUrl';

    /**
     * @event DataChangedEvent an event raised when data was changed.
     */
    const EVENT_DATA_CHANGED = 'dataChanged';

    /**
     * @event FileValidEvent an event raised before file saving.
     * You can set [[FileValidEvent::$valid]] property as false to stop saving process.
     */
    const EVENT_BEFORE_SAVE = 'beforeSave';

    /**
     * @event FileEvent an event raised after file saving.
     */
    const EVENT_AFTER_SAVE = 'afterSave';

    /**
     * @event BeforeDeleteEvent an event raised before file deleting.
     * You can set [[BeforeDeleteEvent::$valid]] property as false to stop deleting process.
     */
    const EVENT_BEFORE_DELETE = 'beforeDelete';

    /**
     * @event AfterDeleteEvent an event raised after file deleting.
     */
    const EVENT_AFTER_DELETE = 'afterDelete';


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
     * @var Context the context that created this file.
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

    /**
     * @var string|null the format that will be used in magic [[self::__toString()]] method by default.
     */
    public $toStringFormat = null;

    /**
     * @var mixed the scheme value that will be used in magic [[self::__toString()]] method by default.
     */
    public $toStringScheme = false;

    /**
     * @var string data that can be used for manipulating with file from context's storage.
     */
    private $_data;

    /**
     * @var string|null basename of the file. Null meaning file is empty.
     */
    private $_name;

    /**
     * @var string|null temp filepath for uploaded file, and read file path fot initialized file.
     * Null meaning file is empty.
     */
    private $_tempName;

    /**
     * @var string|null mime type of file. Null meaning file is empty.
     */
    private $_type;

    /**
     * @var integer|null file size in bytes. Null meaning file is empty.
     */
    private $_size;

    /**
     * @var integer|null file error. It will be:
     * 
     * - integer|null one of UPLOAD_ERR_* constants values for uploaded file,
     * - UPLOAD_ERR_OK for initialized file,
     * - UPLOAD_ERR_NO_FILE for empty file.
     * 
     */
    private $_error;

    /**
     * Changes structure of base class.
     * @see [[_changeStructure()]]
     * 
     * Checks required property `$context`.
     * @throws InvalidConfigException if required property `$context` is missed or is incorrect.
     */
    public function init()
    {
        $this->_changeStructure();

        parent::init();

        if (!$this->context instanceof Context) {
            throw new InvalidConfigException('An instance of '.Context::className().' is required for '.get_class($this).'.');
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
    public function canGetProperty($name, $checkVars = true, $checkBehaviors = true)
    {
        return parent::canGetProperty($name, $checkVars, $checkBehaviors) ||
            $this->_parseFormatAlias($name) !== false;
    }

    /**
     * @inheridoc
     * Returns url for formatted version of files for properties like `as{Format}`.
     */
    public function __get($name)
    {
        if (!parent::canGetProperty($name) && (false !== $format = $this->_parseFormatAlias($name))) {
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
        if (!parent::canSetProperty($name) && $this->_parseFormatAlias($name) !== false) {
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
        if (!parent::canGetProperty($name) && (false !== $format = $this->_parseFormatAlias($name))) {
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
    public function __unset($name)
    {
        if (!parent::canSetProperty($name) && $this->_parseFormatAlias($name) !== false) {
            throw new InvalidCallException('Unsetting read-only property: ' . get_class($this) . "::$name.");
        }
        parent::__unset($name);
    }

    /**
     * Gets file storage data value.
     * @return string|null data that can be used for manipulating with file from context's storage.
     * Null will be returned for empty files.
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * Sets file storage data value.
     * @param string $data data that can be used for manipulating with file from context's storage.
     * @param boolean $resetCalculated whether method must rese calculated properties if data was changed.
     * Default is true.
     */
    public function setData($data, $resetCalculated = true)
    {
        $oldData = $this->_data;
        $this->_data = $data;

        if ($oldData === $data) {
            return;
        }

        if ($resetCalculated) {
            $this->resetCalculatedProperties();
        }
        if (!$this->hasEventHandlers(self::EVENT_DATA_CHANGED)) {
            return;
        }

        $event = new DataChangedEvent();
        $event->sender = $this;
        $event->oldFileData = $oldData;
        $event->newFileData = $data;

        $this->trigger(self::EVENT_DATA_CHANGED, $event);
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
        $this->setData(null, false);
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
        $this->setData($data, false);
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
        $this->setData(null, false);
    }

    /**
     * Gets  basename of the file.
     * @param string $format the name of formatted file version. Null (default) meaning source file.
     * @return string|null basename of the file. Null meaning file is empty.
     * @throws InvalidCallException if `$format` is not null but file has not been initialized yet.
     */
    public function getName($format = null)
    {
        if ($format === null && $this->_name !== null) {
            return $this->_name;
        }
        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            $storage = $this->context->getStorage();
            if ($storage instanceof Storage) {
                $result = $storage->getBaseName($this->getData(), $format);
            } else {
                $result = urldecode(FileSystemHelper::basename($this->getUrl($format)));
            }
            if ($format === null) {
                $this->_name = $result;
            }
            return $result;
        } elseif ($format !== null) {
            throw new InvalidCallException('You cannot call ' . __FUNCTION__ . ' for formatted version of file when the last has not been initialized yet.');
        }
        return null;
    }

    /**
     * Sets basename of the file.
     * @param string|null $name basename of the file. Null meaning file is empty.
     */
    public function setName($name)
    {
        $this->_name = $name;
    }

    /**
     * Gets temp filepath or read filepath for the file.
     * @param string $format the name of formatted file version. Null (default) meaning source file.
     * @return string|null temp filepath for uploaded file, and read file path fot initialized file.
     * Null meaning file is empty.
     * @throws InvalidCallException if `$format` is not null but file has not been initialized yet.
     */
    public function getTempName($format = null)
    {
        if ($format === null && $this->_tempName !== null) {
            return $this->_tempName;
        }
        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            $result = $this->context->getStorage()->getReadFilePath($this->getData(), $format);
            if ($format === null) {
                $this->_tempName = $result;
            }
            return $result;
        } elseif ($format !== null) {
            throw new InvalidCallException('You cannot call ' . __FUNCTION__ . ' for formatted version of file when the last has not been initialized yet.');
        }
        return null;
    }

    /**
     * Sets temp filepath or read filepath for the file.
     * @param string|null $tempName temp filepath for uploaded file, and read file path fot initialized file.
     * Null meaning file is empty.
     */
    public function setTempName($tempName)
    {
        $this->_tempName = $tempName;
    }

    /**
     * Gets mime type of file.
     * @param string $format the name of formatted file version. Null (default) meaning source file.
     * @return string|null mime type of file. Null meaning file is empty.
     * @throws InvalidCallException if `$format` is not null but file has not been initialized yet.
     */
    public function getType($format = null)
    {
        if ($format === null && $this->_type !== null) {
            return $this->_type;
        }
        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            $storage = $this->context->getStorage();
            if ($storage instanceof Storage) {
                $result = $storage->getMimeType($this->getData(), $format);
            } else {
                $tempName = $this->getTempName($format);
                $result = FileSystemHelper::getMimeType($tempName);
            }
            if ($format === null) {
                $this->_type = $result;
            }
            return $result;
        } elseif ($format !== null) {
            throw new InvalidCallException('You cannot call ' . __FUNCTION__ . ' for formatted version of file when the last has not been initialized yet.');
        }
        return null;
    }

    /**
     * Sets mime type of file.
     * @param string|null $type mime type of file. Null meaning file is empty.
     */
    public function setType($type)
    {
        $this->_type = $type;
    }

    /**
     * Gets file size in bytes.
     * @param string $format the name of formatted file version. Null (default) meaning source file.
     * @param string $format the name of formatted file version. Null (default) meaning source file.
     * @return integer|null file size in bytes. Null meaning file is empty.
     * @throws InvalidCallException if `$format` is not null but file has not been initialized yet.
     */
    public function getSize($format = null)
    {
        if ($format === null && $this->_size !== null) {
            return $this->_size;
        }
        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            $storage = $this->context->getStorage();
            if ($storage instanceof Storage) {
                $result = $this->_size = $storage->getFileSize($this->getData(), $format);
            } else {
                $tempName = $this->getTempName($format);
                $result = filesize($tempName);
            }
            if ($format === null) {
                $this->_size = $result;
            }
            return $result;
        } elseif ($format !== null) {
            throw new InvalidCallException('You cannot call ' . __FUNCTION__ . ' for formatted version of file when the last has not been initialized yet.');
        }
        return null;
    }

    /**
     * Sets file size in bytes.
     * @param integer|nul $size file size in bytes. Null meaning file is empty.
     */
    public function setSize($size)
    {
        $this->_size = $size;
    }

    /**
     * Gets file error.
     * @return integer|null $error file error. It will be:
     * 
     * - integer|null one of UPLOAD_ERR_* constants values for uploaded file,
     * - UPLOAD_ERR_OK for initialized file,
     * - UPLOAD_ERR_NO_FILE for empty file.
     * 
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
     * Sets file error. 
     * @param integer|null $error file error. It can be:
     * 
     * - integer|null one of UPLOAD_ERR_* constants values for uploaded file,
     * - UPLOAD_ERR_OK for initialized file,
     * - UPLOAD_ERR_NO_FILE for empty file.
     * 
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
    public function getDefaultUrl($format = null, $scheme = false)
    {
        if (is_array($this->defaultUrls)) {
            if ($format !== null && (isset($this->defaultUrls[$format]) || array_key_exists($format, $this->defaultUrls))) {
                $url = $this->defaultUrls[$format];
            } elseif (isset($this->defaultUrls[0])) {
                $url = $this->defaultUrls[0];
            }
        } else {
            $url = $this->defaultUrls;
        }

        return isset($url) ? Url::to($url, $scheme) : null;
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
    public function getUrl($format = null, $scheme = false)
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

        $event = new CannotGetUrlEvent();
        $event->sender = $this;
        $event->case = $case;
        $event->format = $format;
        $event->scheme = $scheme;
        $event->exception = isset($exception) ? $exception : null;

        $this->trigger(self::EVENT_CANNOT_GET_URL, $event);

        if ($event->handled) {
            return $event->url;
        }
        CannotGetUrlHandlers::throwException($event);
    }

    /**
     * Generates formatted version of file.
     * @param string $format name of format that must be generated.
     * @param boolean $regenerate whether formatted version of file must be regenerated.
     * If false and formatted version exists the method will not start generate process.
     * @throws InvalidCallException if file has not initialized yet.
     */
    public function generateFormat($format, $regenerate = false)
    {
        if ($this->status !== self::STATUS_INITIALIZED_FILE) {
            if ($this->status === self::STATUS_UPLOADED_FILE) {
                throw new InvalidCallException('Cannot generate format for just uploaded file.');
            } else {
                throw new InvalidCallException('Cannot generate format for empty file attribute.');
            }
        }

        $data = $this->context->generateFormat($this->getData(), $format, $regenerate);
        $this->setData($data);
    }

    /**
     * Generates set of formatters. This method optimizes formats generating according to [[FormatterFrom]] formatters.
     * 
     * @param array|null $formats array names of formats that must be generated.
     * Null meaning all formats will be generated.
     * @param boolean $regenerate whether formatted version of file must be regenerated.
     * If false and formatted version exists the method will not start generate process.
     * @throws InvalidCallException if file has not initialized yet.
     */
    public function generateFormats($formats = null, $regenerate = false)
    {
        if ($this->status !== self::STATUS_INITIALIZED_FILE) {
            if ($this->status === self::STATUS_UPLOADED_FILE) {
                throw new InvalidCallException('Cannot generate format for just uploaded file.');
            } else {
                throw new InvalidCallException('Cannot generate format for empty file attribute.');
            }
        }

        $data = $this->context->generateFormats($this->getData(), $formats, $regenerate);
        $this->setData($data);
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
            if ($this->status === self::STATUS_INITIALIZED_FILE) {
                return $this->getUrl($this->toStringFormat, $this->toStringScheme);
            } else {
                // when validator is generating errors for just uploaded file
                // it adds file object to translation params,
                // but the file cannot get url for just uploaded file
                $name = $this->getName();
                return $name === null ? '' : $name;
            }
        } catch (\Exception $e) {
            ErrorHandler::convertExceptionToError($e);
            return '';
        }
    }

    /**
     * Clears all calculated properties: `name`, `tempName`, `type`,
     * `size` and `error.`
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
        if ($this->getError() != UPLOAD_ERR_OK) {
            return false;
        }
        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            return false;
        }
        if (!$this->beforeSave()) {
            return false;
        }

        $tempFile = $this->getTempName();
        $data = $this->context->saveFile($tempFile, $this->getName());

        if ($data === false) {
            return false;
        }

        if ($deleteTempFile && is_uploaded_file($tempFile) && !@unlink($tempFile)) {
            Yii::warning("Cannot remove temporary uploaded file '$tempFile'.", __METHOD__);
        }

        $this->populateFileData($data);
        $this->afterSave();
        return true;
    }

    /**
     * Method that will be called before saving file.
     * If you override this method don't forget call parent::beforeSave().
     * @return boolean whether continue saving process.
     */
    public function beforeSave()
    {
        if (!$this->hasEventHandlers(self::EVENT_BEFORE_SAVE)) {
            return true;
        }

        $event = new FileValidEvent();
        $event->sender = $this;
        $event->valid = true;

        $this->trigger(self::EVENT_BEFORE_SAVE, $event);
        return (bool) $event->valid;
    }

    /**
     * Method that will be called before file deleting.
     * If you override this method don't forget call parent::afterSave().
     */
    public function afterSave()
    {
        if (!$this->hasEventHandlers(self::EVENT_AFTER_SAVE)) {
            return;
        }

        $event = new FileEvent();
        $event->sender = $this;

        $this->trigger(self::EVENT_AFTER_SAVE, $event);
    }

    /**
     * Method that will be called before file deleting.
     * If you override this method don't forget call parent::beforeDelete().
     * @param string $format  string name of format which will be deleted.
     * Null meaning source file was deleted.
     * @return boolean whether need to continue deleting process.
     */
    public function beforeDelete($format = null)
    {
        if (!$this->hasEventHandlers(self::EVENT_BEFORE_DELETE)) {
            return true;
        }

        $event = new BeforeDeleteEvent();
        $event->sender = $this;
        $event->valid = true;
        $event->format = $format;

        $this->trigger(self::EVENT_BEFORE_DELETE, $event);
        return (bool) $event->valid;
    }

    /**
     * Method that will be called after deleting file.
     * If you override this method don't forget call parent::afterDelete().
     * @param string $format  string name of format which will be deleted.
     * Null meaning source file was deleted.
     */
    public function afterDelete($format = null)
    {
        if (!$this->hasEventHandlers(self::EVENT_AFTER_DELETE)) {
            return;
        }

        $event = new AfterDeleteEvent();
        $event->sender = $this;
        $event->format = $format;

        $this->trigger(self::EVENT_AFTER_DELETE, $event);
    }

    /**
     * Deletes file.
     * 
     * This method triggers 'beforeDelete' and 'afterDelete' events.
     * You can set [[FileEvent::$valid]] property as false in 'beforeDelete' event handler for stopping deleting process.
     * 
     * @param string|null $format string $format  string name of format which will be deleted.
     * Null meaning source file was deleted.
     * @return boolean whether file has been successfully deleted.
     */
    public function delete($format = null)
    {
        if ($format !== null) {
            return $this->deleteFormat($format);
        }

        if (!$this->beforeDelete()) {
            return false;
        }

        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            $storage = $this->context->getStorage();
            if ($storage instanceof Storage) {
                $result = $storage->deleteFile($this->getData());
            } else {
                $result = false;
            }
        } elseif ($this->status === self::STATUS_UPLOADED_FILE) {
            $result = (bool) @unlink($this->getTempName());
        } else {
            $result = false;
        }

        if ($result) {
            $this->clearFile();
            $this->afterDelete();
        }
        return $result;
    }

    /**
     * Deletes formatted version of file.
     * @param string $format name of format that must be deleted.
     * @return boolean whether file was deleted.
     */
    public function deleteFormat($format)
    {
        if ($this->status !== self::STATUS_INITIALIZED_FILE) {
            return false;
        } elseif (!$this->beforeDelete($format)) {
            return false;
        }

        $storage = $this->context->getStorage();
        if ($storage instanceof Storage) {
            $result = $storage->deleteFormattedFile($this->getData(), $format);
        } else {
            $result = false;
        }

        if ($result) {
            $this->afterDelete($format);
        }
        return $result;
    }

    /**
     * Note! This method returns filename (like in `pathinfo(..., PATHINFO_FILENAME)), NOT basename (like `basename()`).
     * It is used because the same logic was in [[\yii\web\UploadedFile]] (for back capability).
     * 
     * @param string $format the name of format.
     * @inheritdoc
     */
    public function getBaseName($format = null)
    {
        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            $storage = $this->context->getStorage();
            if ($storage instanceof Storage) {
                return $storage->getFileName($this->getData(), $format);
            }
        }
        return FileSystemHelper::filename($this->getName($format));
    }

    /**
     * @param string $format the name of format.
     * @inheritdoc
     */
    public function getExtension($format = null)
    {
        if ($this->status === self::STATUS_INITIALIZED_FILE) {
            $storage = $this->context->getStorage();
            if ($storage instanceof Storage) {
                return $storage->getExtension($this->getData(), $format);
            }
        }
        return FileSystemHelper::extension($this->getName($format));
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
        return $this->status === self::STATUS_INITIALIZED_FILE && 
            $this->context->getStorage()->fileExists($this->getData(), $format);
    }

    /**
     * Returns array names of exists formatted versions.
     * @return array available formats names for the file.
     */
    public function getFormatList()
    {
        return $this->context->getStorage()->getFormatList($this->getData());
    }

    /**
     * Renders image tag with 'src' and 'alt' attributes.
     * Attribute 'src' will be filled by image url, 'alt' attribute will be filled by file name.
     * 
     * @param string|null $format a name of format. Null meaning url for the source file must be used.
     * @param boolean|string $scheme the URI scheme to use in the generated URL:
     *
     * - `false` (default): generating a relative URL.
     * - `true`: generating an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::hostInfo]].
     * - string: generating an absolute URL with the specified scheme (either `http` or `https`).
     * 
     * @param array $options array of img attributes values.
     * 
     * @return string rendered img tag.
     */
    public function img($format = null, $scheme = false, $options = [])
    {
        $src = $this->getUrl($format, $scheme);
        $options['alt'] = isset($options['alt']) ? $options['alt'] : $this->getName();
        return Html::img($src, $options);
    }
}
