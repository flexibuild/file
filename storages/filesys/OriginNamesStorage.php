<?php

/*
$someModel->image - file property
$someModel->imageFile->url
$someModel->imageFile->absoluteUrl
$someModel->imageFile->getUrl('small')

    контексты настраиваются для урл менеджера
    сторадж вешается на контекст
    на каждую модель вешается бихейвиор с указанием имя столбца => контекст
    не забыть про ивенты
    $someModel->imageFile возвращает класс File, даже если нету файла, т.к. лучше экспешен
    чем фатал еррор
    ивенты скорее всего лучше вешать на объекты File
    валидатор на поле не обязательно вешать, наверное даже можно вешать required валидатор
    валидировать надо на уровне бихейвеора
*/

namespace flexibuild\file\storages\filesys;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Exception;

use flexibuild\file\storages\AbstractStorage;
use flexibuild\file\helpers\CharsetHelper;

/**
 * Storage that keeps your file in local file system but tries save its with
 * origin filenames.
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class OriginNamesStorage extends AbstractStorage
{
    const INFO_KEY_FILENAME = 'filename';
    const INFO_KEY_SUBDIR = 'subdir';

    /**
     * @var string path to directory for saving files. You can use yii aliases.
     * Storage will generate subdirs in this dir.
     */
    public $dir = '@webroot/upload/files';

    /**
     * @var int max count of files in subdirectory.
     */
    public $subdirMaxFilesCount = 1000;

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
     * @var bool whether file system storage must save file with its origin
     * file name or not.
     * If false file system storage will generate new names for new files.
     * If true, but origin filename contains a special char from `$specialChars`
     * file system storage will generate new file too.
     */
    public $saveWithOriginFilename = true;

    /**
     * If `$saveWithOriginFilename` == true and origin filename contains
     * one of this chars storage will generate name for new file.
     * Used for validating filenames in [[validateFileInfo()]] too.
     * @var string local file system special chars.
     */
    public $specialChars = '\/?<>\:*|"';

    /**
     * Used only for `$saveWithOriginFilename` == true. If length of
     * origin filename is bigger than `$maxLength` storage will generate
     * new filename.
     * @var integer max length for filenames.
     */
    public $maxLength = 255;

    /**
     * Used only for `$saveWithOriginFilename` == true and only for windows platforms.
     * Origin filename will be converted in this charset by iconv() method.
     * 
     * Null meaning storage will try detect filesystem charset.
     * @see flexibuild\file\helpers\CharsetHelper
     * 
     * False meaning storage will not convert filename.
     * 
     * @var mixed string|null|false
     */
    public $winFSCharset;

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
        if (!($data = @unserialize($fileInfo))) {
            throw new \Exception('Cannot unserialize file info.');
        }
        if (empty($data[self::INFO_KEY_SUBDIR]) || empty($data[self::INFO_KEY_FILENAME])) {
            throw new \Exception('File info data must contain '.self::INFO_KEY_SUBDIR.' and '.self::INFO_KEY_FILENAME.' parts.');
        }

        $dir = $this->checkedDirectory();
        $subdir = $data[self::INFO_KEY_SUBDIR];
        $filename = $data[self::INFO_KEY_FILENAME];

        switch (false) {
            case $this->matchesRandomChars($subdir):
                throw new \Exception("Invalid subdirectory '$subdir'.");
            case is_dir("$dir/$subdir"):
                throw new \Exception("Directory not found '$dir/$subdir'.");
        }

        switch (false) {
            case $this->validateFilename($filename):
                throw new \Exception("Invalid file name '$filename'.");
            case $fsFilename = $this->getFSFileName($filename):
            case file_exists("$dir/$subdir/$fsFilename"):
                throw new \Exception("File not found '$dir/$subdir/$filename'.");
        }
    }

    /**
     * @inheritdoc
     */
    protected function saveContentInStorage($content, $originFilename = null)
    {
        $resultPath = $this->createFile($originFilename, $content);
        return serialize([
            self::INFO_KEY_FILENAME => basename($resultPath),
            self::INFO_KEY_SUBDIR => basename(dirname($resultPath)),
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function deleteFileFromStorage($fileInfo)
    {
        $data = unserialize($fileInfo);
        $path = $this->checkedDirectory() .
            '/' . $data[self::INFO_KEY_SUBDIR] .
            '/' . $this->getFSFileName($data[self::INFO_KEY_FILENAME]);
        return (bool) @unlink($path);
    }

    /**
     * @inheritdoc
     */
    public function getAllFiles()
    {
        $dir = $this->checkedDirectory();
        $result = [];

        foreach (scandir($dir) as $subdir) {
            if ($subdir === '.' || $subdir === '..') {
                continue;
            }
            if (!$this->matchesRandomChars($subdir) || !is_dir("$dir/$subdir")) {
                continue;
            }

            foreach (scandir("$dir/$subdir") as $file) {
                if ($file === '.' || $file === '..' || !is_file("$dir/$subdir/$file")) {
                    continue;
                }
                try {
                    $filename = $this->convertFromFSFilename($file);
                } catch (\Exception $ex) {
                    continue;
                }
                $result[] = serialize([
                    self::INFO_KEY_FILENAME => $filename,
                    self::INFO_KEY_SUBDIR => $subdir,
                ]);
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function getOptimizedFileUrlWrapper($fileInfo)
    {
        $data = unserialize($fileInfo);
        $path = $this->checkedDirectory() .
            '/' . $data[self::INFO_KEY_SUBDIR] .
            '/' . $this->getFSFileName($data[self::INFO_KEY_FILENAME]);
        return $path;
    }

    /**
     * @inheritdoc
     */
    protected function getWebPath($fileInfo)
    {
        $data = unserialize($fileInfo);
        return rtrim(Yii::getAlias($this->webPath), '/') .
            '/' . $data[self::INFO_KEY_SUBDIR] .
            '/' . rawurlencode($data[self::INFO_KEY_FILENAME]);
    }

    /**
     * Creates new file and return it full path.
     * @param string $originFilename
     * @param string $content
     * @return string
     * @throws Exception if cannot save file or change file permissions.
     */
    protected function createFile($originFilename, $content)
    {
        $this->processFilename($originFilename, $fsFilename, $resultFilename);
        $dir = $this->generateSubDir($fsFilename);
        $filepath = "$dir/$fsFilename";
        $result = "$dir/$resultFilename";

        if (false === @file_put_contents($filepath, $content)) {
            throw new Exception("Cannot create file '$result'.");
        }
        if (!chmod($filepath, $this->createFileMode)) {
            throw new Exception('Cannot change permissions to 0'.base_convert($this->createFileMode, 10, 8)." for file '$result'.");
        }
        return $result;
    }

    /**
     * Handles input filename using `$saveWithOriginFilename` property.
     * @param string $filename input file name.
     * @param string $fsFilename output file name for saving in file system.
     * @param string $resultFilename output file name for saving as data
     * (e.g.: in database).
     */
    protected function processFilename($filename, &$fsFilename, &$resultFilename)
    {
        $fsFilename = $resultFilename = $filename;

        if (mb_strlen($filename, Yii::$app->charset) && $this->saveWithOriginFilename) {
            if ($this->isWindowsOS()) {
                if ($detectedCharset = $this->detectedFSCharset()) {
                    $convertedFilename = @iconv(Yii::$app->charset, $detectedCharset, $filename);
                    if ($convertedFilename && $this->validateFilename($convertedFilename)) {
                        if ($decodedFilename = @iconv($detectedCharset, Yii::$app->charset, $convertedFilename)) {
                            $fsFilename = $convertedFilename;
                            $resultFilename = $decodedFilename;
                            return;
                        }
                    }
                } elseif ($this->validateFilename($filename)) { // fsCharset == false that meaning saving without convertion
                    return;
                }
            } elseif ($this->validateFilename($filename)) { // is not win platform
                return;
            }
        }

        $fsFilename = $resultFilename = $this->generateFilename($filename);
    }

    /**
     * Handles input filename using `$saveWithOriginFilename` property and
     * return filename in file system charset.
     * @param string $filename
     * @return string processed filename.
     * @throws Exception if cannot convert filename.
     */
    public function getFSFileName($filename)
    {
        $pathinfo = pathinfo($filename);
        if ($this->matchesRandomChars($pathinfo['filename']) && (!isset($pathinfo['extension']) || preg_match($this->extensionRegular, $pathinfo['extension']))) {
            return $filename;
        }

        if ($this->saveWithOriginFilename && $this->isWindowsOS() && ($detectedCharset = $this->detectedFSCharset(true))) {
            $convertedFilename = @iconv(Yii::$app->charset, $detectedCharset, $filename);
            if ($convertedFilename) {
                return $convertedFilename;
            } else {
                throw new Exception("Cannot convert filename '$filename' to local file system charset '$detectedCharset'.");
            }
        }
        return $filename;
    }

    /**
     * Handles input filename using `$saveWithOriginFilename` property and
     * return filenames converted to application charset from file system charset.
     * @param string $fsFilename
     * @return string processed filename.
     * @throws Exception if cannot convert filename.
     */
    public function convertFromFSFilename($fsFilename)
    {
        $pathinfo = pathinfo($fsFilename);
        if ($this->matchesRandomChars($pathinfo['filename']) && (!isset($pathinfo['extension']) || preg_match($this->extensionRegular, $pathinfo['extension']))) {
            return $fsFilename;
        }

        if ($this->saveWithOriginFilename && $this->isWindowsOS() && ($detectedCharset = $this->detectedFSCharset(true))) {
            $filename = @iconv($detectedCharset, Yii::$app->charset, $fsFilename);
            if ($filename && $fsFilename === $this->getFSFileName($filename)) {
                return $filename;
            } else {
                throw new Exception("Cannot convert file system name '$fsFilename' to application charset '".Yii::$app->charset."'.");
            }
        }
        return $filename;
    }

    /**
     * Checks input filename for length and special chars.
     * @param string $filename
     * @return bool whether input filename is valid or not.
     * @see $maxLength
     * @see $specialChars
     */
    public function validateFilename($filename)
    {
        $len = strlen($filename);
        return $len > 0 &&
            $len < $this->maxLength &&
            !$this->containsSpecialChar($filename);
    }

    /**
     * Checks input string for special chars.
     * @param string $str
     * @return boolean whether input string contains special chars or not.
     * @see $specialChars
     */
    protected function containsSpecialChar($str)
    {
        $charListLen = mb_strlen($this->specialChars, Yii::$app->charset);
        for ($i = 0; $i < $charListLen; ++$i) {
            $char = mb_substr($this->specialChars, $i, 1, Yii::$app->charset);
            if (mb_strpos($str, $char) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detects and returns file system charset.
     * @param bool $cutSpecialModes value that indicates whether method must
     * cut special modes (like //TRANSLIT or //IGNORE) or not.
     * @return string|bool detected charset. False meaning saving without encode.
     */
    protected function detectedFSCharset($cutSpecialModes = false)
    {
        if (null === $result = $this->winFSCharset) {
            $detectedCharset = CharsetHelper::getFileSystemCharset();
            $result = $detectedCharset ? "$detectedCharset//TRANSLIT" : false;
        }

        if ($cutSpecialModes && strcasecmp(substr($result, -10), '//TRANSLIT') === 0) {
            $result = substr($result, 0, -10);
        } elseif ($cutSpecialModes && strcasecmp(substr($result, -8), '//IGNORE') === 0) {
            $result = substr($result, 0, -8);
        }

        return $result;
    }

    /**
     * Detects Windows platform.
     * @return bool whether current operating system is Windows or not.
     */
    protected function isWindowsOS()
    {
        return strcasecmp(substr(PHP_OS, 0, 3), 'WIN') === 0;
    }

    /**
     * Generates new subdir in directory from config.
     * @param string $fsFilename filename in file system charset.
     * @return string full path to new generated subdirectory.
     * @throws Exception if the dir is invalid or cannot create the dir.
     * @see $dir
     */
    protected function generateSubDir($fsFilename)
    {
        $dir = $this->checkedDirectory();
        foreach (scandir($dir) as $subdir) {
            if ($subdir === '.' || $subdir === '..' || !is_dir($subdir)) {
                continue;
            }
            if (file_exists("$dir/$subdir/$filename")) {
                continue;
            }

            $filesCount = array_reduce(scandir("$dir/$subdir"), function ($result, $file) {
                return $result += ($file === '.' || $file === '..') ? 0 : 1;
            }, 0);
            if ($filesCount >= $this->subdirMaxFilesCount) {
                continue;
            }

            return "$dir/$subdir";
        }

        while (is_dir($result = "$dir/".$this->generateRandomString()));
        @mkdir($result, $this->makeDirMode, true);
        if (!is_dir($result)) {
            throw new Exception("Cannot create subdirectory '$result'.");
        }
        return $result;
    }

    /**
     * Creates and returns new generated filename.
     * @param string $originFilename source filename.
     * @return string generated filename.
     */
    protected function generateFilename($originFilename)
    {
        $ext = (string) @pathinfo($originFilename, PATHINFO_EXTENSION);
        if ($ext === '' || !preg_match($this->extensionRegular, $ext)) {
            $ext = false;
        }
        return $this->generateRandomFilename().($ext !== false ? ".$ext" : '');
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
