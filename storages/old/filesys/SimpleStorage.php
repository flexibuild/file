<?php

namespace flexibuild\file\storages\filesys;

use Yii;
use yii\helpers\FileHelper;
use yii\base\InvalidConfigException;
use yii\base\Exception;

use flexibuild\file\storages\AbstractStorage;

/**
 * Storage that keeps your files in local file system. Every new file will
 * have new generated name.
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class SimpleStorage extends AbstractStorage
{
    /**
     * @var string path to directory for saving files. You can use yii aliases.
     * Storage will generate new files in this dir.
     */
    public $dir = '@webroot/upload/files';

    /**
     * @var string web path to saved files. You can use yii aliases.
     */
    public $webPath = '@web/upload/files';

    /**
     * @var int mode for creating new directories.
     */
    public $makeDirMode = 0755;

    /**
     * @var int mode for creating new files.
     */
    public $createFileMode = 0755;

    /**
     * @var int string length for new generated filenames.
     */
    public $generateStringLength = 16;

    /**
     * @var string regular expression. Used for new generated filenames.
     * If extension of origin filename does not match this expression
     * file without extension will be saved.
     */
    public $extensionRegular = '/^[a-z0-9_\-]+$/i';

    /**
     * Checks file info and throws exception if it is incorrect.
     * @param string $fileInfo
     * @throws \Exception if file info has incorrect format.
     */
    protected function checkFileInfo($fileInfo)
    {
        $dir = $this->checkedDirectory();
        $filename = @pathinfo($fileInfo, PATHINFO_FILENAME);
        $extension = @pathinfo($fileInfo, PATHINFO_EXTENSION);
        $basename = $filename . ($extension ? '' : ".$extension");

        switch (false) {
            case $basename === $fileInfo:
                throw new \Exception("File info '$fileInfo' contains some wrong data.");
            case $this->matchesRandomChars($filename):
                throw new \Exception("Invalid file name '$filename'.");
            case !$extension || preg_match($this->extensionRegular, $extension):
                throw new \Exception("Invalid file extension '$extension'.");
            case is_file("$dir/$basename"):
                throw new \Exception("File not found '$dir/$basename'.");
        }
    }

    /**
     * @inheritdoc
     */
    protected function saveContentInStorage($content, $originFilename = null)
    {
        $filepath = $this->generateFilename($originFilename);
        $result = basename($filepath);

        if (false === @file_put_contents($filepath, $content)) {
            throw new Exception("Cannot create file '$result'.");
        }
        if (!chmod($filepath, $this->createFileMode)) {
            throw new Exception('Cannot change permissions to 0'.base_convert($this->createFileMode, 10, 8)." for file '$result'.");
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function deleteFileFromStorage($fileInfo)
    {
        $path = $this->checkedDirectory() . '/' . $fileInfo;
        return (bool) @unlink($path);
    }

    /**
     * @inheritdoc
     */
    public function getAllFiles()
    {
        $dir = $this->checkedDirectory();
        $files = FileHelper::findFiles($dir, [
            'recursive' => false,
        ]);

        $result = [];
        foreach ($files as $filepath) {
            $filename = @pathinfo($filepath, PATHINFO_FILENAME);
            $extension = @pathinfo($filepath, PATHINFO_EXTENSION);
            if ($this->matchesRandomChars($filename) && (!$extension || preg_match($this->extensionRegular, $extension))) {
                $result[] = basename($filepath);
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function getOptimizedFileUrlWrapper($fileInfo)
    {
        $path = $this->checkedDirectory() . '/' . $fileInfo;
        return $path;
    }

    /**
     * @inheritdoc
     */
    protected function getWebPath($fileInfo)
    {
        return rtrim(Yii::getAlias($this->webPath), '/') . '/' . $fileInfo;
    }

    /**
     * Creates and returns new generated filename.
     * @param string $originFilename source filename.
     * @return string full path to generated filename.
     */
    protected function generateFilename($originFilename)
    {
        $ext = (string) @pathinfo($filename, PATHINFO_EXTENSION);
        if ($ext === '' || !preg_match($this->extensionRegular, $ext)) {
            $ext = '';
        } else {
            $ext = ".$ext";
        }

        $dir = $this->checkedDirectory();
        while (file_exists($filename = "$dir/".$this->generateRandomString().$ext));
        return $filename;
    }

    /**
     * Generates random string.
     * @return string
     */
    protected function generateRandomString()
    {
        return Yii::$app->security->generateRandomString($this->generateStringLength);
    }

    /**
     * Checkes whether input `$string` chars matches all possible random chars or not.
     * @param stirng $string
     * @return bool whether input `$string` chars matches all possible random chars or not.
     */
    protected function matchesRandomChars($string)
    {
        return preg_match('/^[a-z0-9_-]+$/i', $string);
    }

    /**
     * Makes directory if not exists and returns it.
     * @return string path to directory.
     * @throws InvalidConfigException if the dir is invalid or cannot create
     * the dir.
     */
    protected function checkedDirectory()
    {
        $dir = rtrim(Yii::getAlias($this->dir), '\/');
        @mkdir($dir, $this->makeDirMode, true);
        if (!is_dir($dir)) {
            throw new InvalidConfigException("Incorrect directory '$this->dir'.");
        }
        return $dir;
    }
}
