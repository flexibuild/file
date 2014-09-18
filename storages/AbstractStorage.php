<?php

namespace flexibuild\file\storages;

use Yii;
use yii\base\Component;
use yii\web\Request;

use yii\base\NotSupportedException;
use yii\base\InvalidConfigException;
use yii\base\Exception;
use flexibuild\file\exceptions\InvalidFormatException;

/**
 * @property-read array $allFiles all file storage files. Each element is data
 * of saved file.
 * @property string $hostInfo The host info (e.g. "http://www.example.com") is used
 * for creating absolute urls.
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
abstract class AbstractStorage extends Component
{
    /**
     * @event SaveFileEvent an event raised in the beginning of [[saveFile()]] and [[saveContent()]].
     */
    const EVENT_BEFORE_SAVE = 'beforeSave';
    /**
     * @event SaveFileEvent an event raised in the end of [[saveFile()]] and [[saveContent()]].
     */
    const EVENT_AFTER_SAVE = 'afterSave';
    /**
     * @event DeleteFileEvent an event raised in the beginning of [[deleteFile()]].
     */
    const EVENT_BEFORE_DELETE = 'beforeDelete';
    /**
     * @event DeleteFileEvent an event raised in the end of [[deleteFile()]].
     */
    const EVENT_AFTER_DELETE = 'afterDelete';

    private $_hostInfo;

    /**
     * Validates file info. If `$throwException` is true than method will return
     * a value that indicates whether input file info is correct or not. If
     * `$throwException` is false method will throw \flexibuild\file\exceptions\InvalidFormatException.
     * @param string $fileInfo file data that must be checked.
     * @param bool $throwException whether method must throw exception or
     * return a boolean value. True by default.
     * @return bool whether file info is correct or not. Used only if 
     * `$throwException` is false.
     * @throws InvalidFormatException if file info has incorrect format. Used
     * only if `$throwException` is true.
     */
    public function validateFileInfo($fileInfo, $throwException = true)
    {
        try {
            $this->checkFileInfo($fileInfo);
        } catch (\Exception $ex) {
            if ($throwException) {
                throw new Exception("File info '$fileInfo' has incorrect format. ".$ex->getMessage(), $ex->getCode(), $ex);
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks file info and throws exception if it is incorrect.
     * Method must be implemented in child classes. It used by [[validateFileInfo()]].
     * @param string $fileInfo data of file.
     * @throws \Exception if file is incorrect.
     */
    abstract protected function checkFileInfo($fileInfo);

    /**
     * Saves content in new file of storage and returns file identifier info.
     * Method must be implemented in child classes. It used by [[saveFile()]]
     * and [[saveContent()]].
     * @param string $content for saving in file.
     * @param string $originFilename User file name if exists.
     * @return string data of new saved file.
     * This data must be saved by file handler.
     */
    abstract protected function saveContentInStorage($content, $originFilename = null);

    /**
     * Saves new file in storage and returns file identifier info.
     * @param string $tmpFilepath Local file path.
     * @param string $originFilename User file name if exists.
     * @return string serialized data of new saved file.
     * This data must be saved by file handler.
     * @throws Exception if cannot read `$tmpFilepath`.
     */
    public function saveFile($tmpFilepath, $originFilename = null)
    {
        $content = @file_get_contents($tmpFilepath);
        if ($content === false) {
            throw new Exception("Cannot read file '$tmpFilepath'.");
        }

        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE, new SaveFileEvent([
                'tmpFilepath' => $tmpFilepath,
                'content' => $content,
                'originFilename' => $originFilename,
            ]));
        }

        $resultInfo = $this->saveContentInStorage($content, $originFilename);

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE)) {
            $this->trigger(self::EVENT_AFTER_SAVE, new SaveFileEvent([
                'tmpFilepath' => $tmpFilepath,
                'content' => $content,
                'originFilename' => $originFilename,
                'resultInfo' => $resultInfo,
            ]));
        }

        return $resultInfo;
    }

    /**
     * Saves content in new file of storage and returns file identifier info.
     * @param string $content for saving in file.
     * @param string $originFilename User file name if exists.
     * @return string data of new saved file.
     * This data must be saved by file handler.
     */
    public function saveContent($content, $originFilename = null)
    {
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE, new SaveFileEvent([
                'content' => $content,
                'originFilename' => $originFilename,
            ]));
        }

        $resultInfo = $this->saveContentInStorage($content, $originFilename);

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE)) {
            $this->trigger(self::EVENT_AFTER_SAVE, new SaveFileEvent([
                'content' => $content,
                'originFilename' => $originFilename,
                'resultInfo' => $resultInfo,
            ]));
        }

        return $resultInfo;
    }

    /**
     * Deletes file by file identifier info.
     * @param string $fileInfo file identifier info.
     * @return bool whether file was successfully deleted or not.
     * @throws NotSupportedException if storage does not support delete feature.
     */
    public function deleteFile($fileInfo)
    {
        $this->validateFileInfo($fileInfo);

        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE)) {
            $this->trigger(self::EVENT_BEFORE_DELETE, new DeleteFileEvent([
                'fileInfo' => $fileInfo,
            ]));
        }

        $result = $this->deleteFileFromStorage();

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE)) {
            $this->trigger(self::EVENT_AFTER_DELETE, new DeleteFileEvent([
                'fileInfo' => $fileInfo,
                'result' => $result,
            ]));
        }

        return $result;
    }

    /**
     * Deletes file by file identifier info.
     * Method for implementation in child classes in storage supports delete feature.
     * It used by [[deleteFile()]].
     * @param string $fileInfo file identifier info. File info has been already
     * checked when this method will called.
     * @return bool whether file was successfully deleted or not.
     * @throws NotSupportedException if storage does not support delete feature.
     */
    protected function deleteFileFromStorage($fileInfo)
    {
        throw new NotSupportedException('Delete feature is not supported in '.get_class($this).'.');
    }

    /**
     * Returns all file storage files. Each element is data of saved file.
     * Method for implementation in child classes if storage supports list feature.
     * @return array of all files datas.
     * @throws NotSupportedException if storage does not support all
     */
    public function getAllFiles()
    {
        throw new NotSupportedException('List all files feature is not supported in '.  get_class($this).'.');
    }

    /**
     * Returns url wrapper for current file. Result of this method can be used in future
     * for getting file content quickly, for example with `file_get_contents()`.
     * If storage can return local file path it must return it, otherwise method
     * can return web url for example.
     * @param string $fileInfo file identificator info.
     * @return string url wrapper.
     */
    public function getFileUrlWrapper($fileInfo)
    {
        $this->validateFileInfo($fileInfo);
        return $this->getOptimizedFileUrlWrapper($fileInfo);
    }

    /**
     * Method for implementation in child classes.
     * Returns url wrapper for current file. Result of this method can be used in future
     * for getting file content quickly, for example with `file_get_contents()`.
     * If storage can return local file path it must override this method.
     * @param string $fileInfo file identificator info. File info has been already
     * checked when this method will called.
     * @return string url wrapper.
     */
    protected function getOptimizedFileUrlWrapper($fileInfo)
    {
        return $this->getWebPath($fileInfo);
    }

    /**
     * Returns url for getting file through web.
     * @param string $fileInfo file identifier info.
     * @param bool whether method must return absolute url or not
     * @return string url for file.
     */
    public function getUrl($fileInfo)
    {
        $this->validateFileInfo($fileInfo);
        return $this->getWebPath($fileInfo);
    }


    /**
     * Returns url for getting file through web.
     * Method for implementation in child classes.
     * @param string $fileInfo file identifier info. File info has been already
     * checked when this method will called.
     * @return string url for file.
     */
    abstract protected function getWebPath($fileInfo);

    /**
     * Returns absolute url for getting file through web.
     * @param string $fileInfo file identifier info.
     * @param string $scheme Null meaning getting it from current url.
     * @param bool whether method must return absolute url or not
     * @return string url for file.
     */
    public function getAbsoluteUrl($fileInfo, $scheme = null)
    {
        $url = $this->getUrl($fileInfo);
        if (strpos($url, '://') === false) {
            $url = $this->getHostInfo() . $url;
        }
        if (is_string($scheme) && ($pos = strpos($url, '://')) !== false) {
            $url = $scheme . substr($url, $pos);
        }
        return $url;
    }

    /**
     * Returns the host info that is used for creating absolute urls.
     * @return string the host info (e.g. "http://www.example.com") that is used for creating absolute urls.
     * @throws InvalidConfigException if running in console application and [[hostInfo]] is not configured.
     */
    public function getHostInfo()
    {
        if ($this->_hostInfo === null) {
            $request = Yii::$app->getRequest();
            if ($request instanceof Request) {
                $this->_hostInfo = $request->getHostInfo();
            } else {
                throw new InvalidConfigException('Please configure '.get_class($this).'::hostInfo correctly as you are running a console application.');
            }
        }
        return $this->_hostInfo;
    }

    /**
     * Sets the host info that is used for creating absolute urls.
     * @param string $value the host info (e.g. "http://www.example.com") that is used for creating absolute URLs.
     */
    public function setHostInfo($value)
    {
        $this->_hostInfo = rtrim($value, '/');
    }
}
