<?php

namespace flexibuild\file\storages;

use Yii;
use yii\base\Object;
use yii\base\InvalidParamException;
use yii\base\NotSupportedException;

use yii\helpers\Url;

use flexibuild\file\helpers\FileSystemHelper;
use flexibuild\file\contexts\Context;

/**
 * Base storage class that implements [[StorageInterface]] and has helpful methods.
 * See documentation of [[StorageInterface]] methods for more info.
 * @see StorageInterface
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 * 
 * @property array $allFiles array of string datas, which of that can be used for manipulating with file.
 */
abstract class Storage extends Object implements StorageInterface
{
    /**
     * Param that will be passed as `$checkExtension` param in [[\yii\helpers\FileHelper]].
     * @var boolean $checkExtension whether to use the file extension to determine the MIME type in case
     * `finfo_open()` cannot determine it.
     */
    public $tryDetectMimeTypeByExtension = false;

    /**
     * @var Context the owner of this storage.
     */
    public $context;

    /*
     * @inheritdoc
     */
    abstract public function saveFile($content, $originFilename = null);

    /**
     * Saves new file by copying from another. Returns data that can be used for manipulating with file in the future.
     * 
     * Note: override this method in subclass if your storage allows copy method (more quickly).
     * 
     * @param string $sourceFilePath path to source file that will be copied.
     * @param string|null $originFilename name of origin file.
     * If storage allowed saving files with origin filenames it must use this param for that.
     * @return string|boolean string data that can be used for manipulating with file in the future.
     * False meaning file was not save.
     */
    public function saveFileByCopying($sourceFilePath, $originFilename = null)
    {
        if (false === $content = @file_get_contents($sourceFilePath)) {
            Yii::warning("Cannot load file contents '$sourceFilePath'.", __METHOD__);
            return false;
        }
        return $this->saveFile($content, $originFilename);
    }

    /**
     * @inheritdoc
     */
    abstract public function saveFormattedFile($data, $content, $formatName, $extension = false);

    /**
     * Saves formatted version of file (e.g. any resized image for source image) by copying from another file with needed content.
     * The method returns new data that can be used for loading source file.
     * 
     * Note: override this method in subclass if your storage allows copy method (more quickly).
     * 
     * @param string $data data that can be used for loading source file.
     * @param string $sourceFilePath path to file with content of the formatted file.
     * @param string $formatName name of the format.
     * @return string|boolean string data that can be used for loading source file int he future.
     * False meaning formatted file was not save.
     */
    public function saveFormattedFileByCopying($data, $sourceFilePath, $formatName)
    {
        if (false === $content = @file_get_contents($sourceFilePath)) {
            Yii::warning("Cannot read content of the file '$sourceFilePath'.", __METHOD__);
            return false;
        }

        $decodedFilePath = FileSystemHelper::decodeFilename($sourceFilePath);
        $extension = $decodedFilePath === false ? false : FileSystemHelper::extension($decodedFilePath);
        return $this->saveFormattedFile($data, $content, $formatName, $extension);
    }

    /**
     * @inheritdoc
     */
    abstract public function fileExists($data, $format = null);

    /**
     * Returns file url for accessing to file through http protocol.
     * Note the method should return relative path if it is possible.
     * 
     * @param string $data data that should be used for loading source file.
     * @param string|null $format a name of format. Null meaning url for the source file must be returned.
     * @return string the url to source or formatted file according to [[$format]] param.
     * The method may return value in any format as there is described in [[\yii\helpers\Url::to()]], see docs for `$url` param.
     */
    abstract protected function getRelativeUrl($data, $format = null);

    /**
     * @inheritdoc
     */
    public function getUrl($data, $format = null, $scheme = false)
    {
        $url = $this->getRelativeUrl($data, $format);
        return Url::to($url, $scheme);
    }

    /**
     * @inheritdoc
     */
    abstract public function getReadFilePath($data, $format = null);

    /**
     * Returns the list of formats that available for the file.
     * @param string $data data that should be used for manipulating with file formats.
     * @return array array of all file formats names.
     */
    abstract public function getFormatList($data);

    /**
     * Searches and returns the list of all available files.
     * You must implement this method in your subclass for getting the file list functionality.
     * @return array array of string datas, which of that can be used for manipulating with file.
     * @throws NotSupportedException
     */
    public function getAllFiles()
    {
        throw new NotSupportedException('The '. get_class($this) .' does not support file list method.');
    }

    /**
     * Validates file data and format (if passed) and throws [[InvalidParamException]] if data and/or format has incorrect format.
     * @throws InvalidParamException if data has incorrect format and/or source (formatted) file does not exist.
     */
    protected function checkFileData($data, $format = null)
    {
        if ($this->fileExists($data, $format)) {
            return;
        }
        if ($format === null || !$this->fileExists($data)) {
            throw new InvalidParamException('File with current data does not exist.');
        } else {
            throw new InvalidParamException("Cannot find version of file that formatted like '$format'.");
        }
    }

    /**
     * Deletes file with all it formatted versions.
     * You must implement it in your subclass for getting the delete functionality.
     * @param string $data data of source file that must be deleted.
     * @return boolean whether the file has been successfully deleted.
     * @throws NotSupportedException
     */
    public function deleteFile($data)
    {
        throw new NotSupportedException('The ' . get_class($this) . ' does not support delete file method.');
    }

    /**
     * Deletes formatted version of file.
     * You must implement it in your subclasses for getting the delete functionality.
     * @param string $data data of source file that formatted version must be deleted.
     * @param string $format the name of format which version of file must be deleted.
     * @return boolean whether formatted version of the file has been successfully deleted.
     * @throws NotSupportedException
     */
    public function deleteFormattedFile($data, $format)
    {
        throw new NotSupportedException('The ' . get_class($this) . ' does not support delete file method.');
    }

    /**
     * Calculates and returns file size in bytes.
     * @param string $data data that should be used for loading source file.
     * @param string|null $format if this param is passed the method will return size of formatted file.
     * @return integer file size in bytes.
     */
    public function getFileSize($data, $format = null)
    {
        $path = $this->getReadFilePath($data, $format);
        return filesize($path);
    }

    /**
     * Calculates and returns file mime type.
     * @param string $data data that should be used for loading source file.
     * @param string|null $format if this param is passed the method will return mime type of formatted file.
     * @return string the MIME type. Null is returned if the MIME type cannot be determined.
     */
    public function getMimeType($data, $format = null)
    {
        $path = $this->getReadFilePath($data, $format);
        return FileSystemHelper::getMimeType($path, null, $this->tryDetectMimeTypeByExtension);
    }

    /**
     * Calculates and returns file base name.
     * @param string $data data that should be used for loading source file.
     * @param string|null $format if this param is passed the method will return base name of formatted file.
     * @return string the MIME type. Null is returned if the MIME type cannot be determined.
     */
    public function getBaseName($data, $format = null)
    {
        $url = $this->getUrl($data, $format);
        return urldecode(FileSystemHelper::basename($url));
    }

    /**
     * Calculates and returns file name.
     * @param string $data data that should be used for loading source file.
     * @param string|null $format if this param is passed the method will return name of formatted file.
     * @return string the MIME type. Null is returned if the MIME type cannot be determined.
     */
    public function getFileName($data, $format = null)
    {
        $url = $this->getUrl($data, $format);
        return urldecode(FileSystemHelper::filename($url));
    }

    /**
     * Calculates and returns file extension.
     * @param string $data data that should be used for loading source file.
     * @param string|null $format if this param is passed the method will return extension of formatted file.
     * @return string the MIME type. Null is returned if the MIME type cannot be determined.
     */
    public function getExtension($data, $format = null)
    {
        $url = $this->getUrl($data, $format);
        return urldecode(FileSystemHelper::extension($url));
    }
}
