<?php

namespace flexibuild\file\storages;

/**
 * Storage interface describes base methods that must be implemented in storage classes.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
interface StorageInterface
{
    /**
     * Saves content to file and returns data that can be used for manipulating with file in the future.
     * @param string $content content of file that will be saved.
     * @param string|null $originFilename name of origin file.
     * If storage allowed saving files with origin filenames it must use this param for that.
     * @return string|boolean string data that can be used for manipulating with file in the future.
     * False meaning file was not save.
     */
    public function saveFile($content, $originFilename = null);

    /**
     * Saves formatted version of file (e.g. any resized image for source image).
     * The method returns new data that can be used for loading source file.
     * @param string $data data that can be used for loading source file.
     * @param string $content content of the formatted file.
     * @param string $formatName name of the format.
     * @param string|boolean $extension the extension of formatted version of file.
     * False (default) meaning file has not extension.
     * @return string|boolean string data that can be used for loading source file int he future.
     * False meaning formatted file was not save.
     */
    public function saveFormattedFile($data, $content, $formatName, $extension = false);

    /**
     * Returns file url for accessing to file through http protocol.
     * @param string $data data that should be used for loading source file.
     * @param string|null $format a name of format. Null meaning url for the source file must be returned.
     * @param boolean|string $scheme the URI scheme to use in the generated URL:
     *
     * - `false` (default): generating a relative URL.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::hostInfo]].
     * - string: generating an absolute URL with the specified scheme (either `http` or `https`).
     * 
     * @return string the url to source or formatted file according to [[$format]] param.
     */
    public function getUrl($data, $format = null, $scheme = false);

    /**
     * Validates data and returns value whether [[$data]] can be used for loading source file.
     * This method should not throw any exception.
     * @param string $data data that must be validated.
     * @param string|null $format if this param is passed the method must validate existing the format of file too.
     * @return boolean false if the data can not be used for loading source or formatted file (if [[$format]] passed), true otherwise.
     */
    public function fileExists($data, $format = null);

    /**
     * Returns path to file that may be used for reading file content.
     * This method will only be used to read file, not for recording.
     * 
     * The need for this method is a higher speed access to a file, unlinke [[getUrl()]].
     * But the method can be implemented with returning any path wrap that supported by `file_get_content()` function.
     * 
     * @param string $data data that should be used for loading source file.
     * @param string|null $format if this param is passed the method should return path for reading formatted version of file.
     * @return string path (or some wrap) that can be used to read file.
     */
    public function getReadFilePath($data, $format = null);

    /**
     * Returns the list of formats that available for the file.
     * @param string $data data that should be used for manipulating with file formats.
     * @return array array of all file formats names.
     */
    public function getFormatList($data);
}
