<?php

namespace flexibuild\file\storages;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\InvalidValueException;
use yii\base\InvalidParamException;
use yii\helpers\StringHelper;

use flexibuild\file\helpers\FileSystemHelper;

/**
 * Storage that keeps your files in local file system.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 * 
 * @property-read string $rootDirectory full path to directory which will be used to store files.
 * @property-read string $rootWebPath web path to directory which will be used to getting urls.
 */
class FileSystemStorage extends Storage
{
    /**
     * @var string path template to directory for saving files. You can use yii aliases.
     * You also can use '{context}' placeholder for inserting the name of the context.
     * Storage will generate subdirectories in this directory. New files will be saved in these subdirectories.
     */
    public $dir = '@webroot/upload/files/{context}';

    /**
     * @var string web path to saved files. You can use yii aliases.
     * You also can use '{context}' placeholder for inserting the name of the context.
     * This property will be used for getting url to file.
     */
    public $webPath = '@web/upload/files/{context}';

    /**
     * @var int mode for creating new directories.
     */
    public $makeDirMode = 0777;

    /**
     * @var int mode for creating new files.
     */
    public $createFileMode = 0777;

    /**
     * @var integer|null max count of files in one subderictory of `$dir` directory.
     */
    public $maxSubdirFilesCount = 1000;

    /**
     * @var int string length for new generated filenames.
     */
    public $fileNamesLength = 16;

    /**
     * @var integer length for new generated subdirectories.
     */
    public $subdirsLength = 4;

    /**
     * @var string regular expression. Used for new generated filenames.
     * If extension of origin filename does not match this expression
     * than file without extension will be saved.
     * Note, this expression limits extension length too.
     */
    public $extensionRegular = '/^[a-z0-9_\-]{1,16}$/i';

    /**
     * @var array extensions that will be disallowed by storage.
     * If file has one of these extensions than file without extension will be saved.
     * Comparing with these extensions will be case insensitive.
     */
    public $disallowedExtensions = [
        'htaccess',
        'php',
        'pl',
        'py',
        'jsp',
        'asp',
        'shtml',
        'sh',
        'cgi',
        'inc',
        'phtml',
    ];

    /**
     * If this property is true the storage will try to save files with them origin names.
     * @var boolean whether storage must try to save file with it origin name.
     */
    public $saveOriginNames = false;

    /**
     * If `$saveOriginNames` is true and origin filename contains
     * one of this chars storage will generate new name for file.
     * Used for validating filenames in [[fileExists()]] too.
     * @var string local file system special chars.
     */
    public $specialChars = '\/?<>\:*|"';

    /**
     * Used only for `$saveOriginNames` == true. If length of
     * origin filename is bigger than this property storage will generate
     * new filename.
     * @var integer max length for filenames.
     */
    public $maxLength = 255;

    /**
     * Used only for `$saveOriginNames` == true and only for windows platforms.
     * Origin filename will be converted in this charset by `iconv()` method.
     * 
     * Null meaning storage will try detect filesystem charset.
     * @see [[\flexibuild\file\helpers\FileSystemHelper::getFileSystemCharset()]]
     * 
     * False meaning storage will not convert filename.
     * 
     * @var mixed string|null|false
     */
    public $winFSCharset = null;

    /**
     * @inheritdoc
     */
    public function saveFile($content, $originFilename = null)
    {
        if ($this->context && $this->context->getDefaultFormatter() !== null) {
            $tmpFileName = FileSystemHelper::getNewTempFilename(FileSystemHelper::extension($originFilename));
            if (false === @file_put_contents($tmpFileName, $content)) {
                throw new InvalidValueException("Cannot save temp file: $tmpFileName");
            }
            $result = $this->_saveFileByCopying($tmpFileName, $originFilename);
            if (!@unlink($tmpFileName)) {
                //!!!
            }
        }

        $filename = $this->createNewFilename($originFilename);
        $fsFilename = FileSystemHelper::encodeFilename($filename, $this->winFSCharset);
        $rootDir = $this->getRootDirectory();
        $folder = $this->getFileFolder($filename);

        if (false === @file_put_contents("$rootDir/$folder/$fsFilename", $content)) {
            Yii::warning("Cannot save file: $rootDir/$folder/$filename", __METHOD__);
            return false;
        }
        return "$folder/$filename";
    }

    /**
     * @inheritdoc
     */
    public function saveFileByCopying($sourceFilePath, $originFilename = null)
    {
        return $this->_saveFileByCopying($sourceFilePath, $originFilename);
    }

    /**
     * Saves new file from another file. Internally used in [[self::saveFile()]] and [[self::saveFileByCopying()]].
     * @param string $sourceFilePath path to file that must be copied.
     * @param string $originFilename the name of origin file.
     * @return string|boolean string file data that must be used for manipulating with file or false if file was not saved.
     */
    private function _saveFileByCopying($sourceFilePath, $originFilename = null)
    {
        $filename = $this->createNewFilename($originFilename);
        $fsFilename = FileSystemHelper::encodeFilename($filename, $this->winFSCharset);
        $rootDir = $this->getRootDirectory();
        $folder = $this->getFileFolder($filename);

        if (!@copy($sourceFilePath, "$rootDir/$folder/$fsFilename")) {
            Yii::warning("Cannot copy file '$sourceFilePath' to '$rootDir/$folder/$filename'.", __METHOD__);
            return false;
        }
        return "$folder/$filename";
    }

    /**
     * @inheritdoc
     */
    public function saveFormattedFile($data, $content, $formatName)
    {
        $fsFormatPath = $this->_getFormatFSFilePath($data, $formatName);
        if (false === @file_put_contents($fsFormatPath, $content)) {
            Yii::warning("Cannot save formatted as '$formatName' version of file '$data' in '{$this->context->name}' context.");
            return false;
        }
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function saveFormattedFileByCopying($data, $sourceFilePath, $formatName)
    {
        $fsFormatPath = $this->_getFormatFSFilePath($data, $formatName);
        if (!@copy($sourceFilePath, $fsFormatPath)) {
            Yii::warning("Cannot copy formatted as '$formatName' version of file ($sourceFilePath) for '$data' file in '{$this->context->name}' context.");
            return false;
        }
        return $data;
    }

    /**
     * Creates directory for formatted version of file and returns full path to formatted version of file.
     * Note returned file path will be in local file system character set.
     * @param string $data data of source file.
     * @param string $formatName the format name.
     * @return string full path to formatted version of file.
     * @throws InvalidParamException if file with `$data` does not exist.
     * @throws InvalidConfigException if cannot create directory for format.
     */
    private function _getFormatFSFilePath($data, $formatName)
    {
        $this->checkFileData($data);

        list($folder, $basename) = explode('/', $data);
        $dirname = $this->getRootDirectory() . "/$folder";
        $formatDir = "$dirname/$formatName";

        if (!FileSystemHelper::createDirectory($formatDir, $this->makeDirMode)) {
            throw new InvalidConfigException("Cannot create directory: $formatDir");
        }
        return "$formatDir/" . FileSystemHelper::encodeFilename($basename, $this->winFSCharset);
    }

    /**
     * @inheritdoc
     */
    public function fileExists($data, $format = null)
    {
        if (!is_string($data) || $data === '') {
            return false;
        }
        if (count($fileParts = explode('/', $data)) !== 2) {
            return false;
        }

        $rootDirectory = $this->getRootDirectory();
        $fsSourceFilename = FileSystemHelper::encodeFilename($fileParts[1], $this->winFSCharset);

        switch (true) {
            case !preg_match('/^[a-z0-9\-\_]+$/i', $fileParts[0]); // no break
            case in_array($fsSourceFilename, ['', '.', '..'], true); // no break
            case $this->containsSpecialChar($fsSourceFilename); // no  break
            case !is_dir($rootDirectory/$fileParts[0]); // no break
            case !FileSystemHelper::fileExists($rootDirectory, $fileParts[0], true, false); // no break
            case !is_file("$rootDirectory/$fileParts[0]/$fsSourceFilename"); // no break
            case !FileSystemHelper::fileExists("$rootDirectory/$fileParts[0]", $fsSourceFilename, true, $this->winFSCharset); // no break
                return false;

            case $format === null:
                return true;

            case !is_string($format); // no break
            case !preg_match('/^[0-9a-z\_]+$/i', $format); // no break
            case !is_dir("$rootDirectory/$fileParts[0]"); // no break
            case !FileSystemHelper::fileExists("$rootDirectory/$fileParts[0]", $format, true, false); // no break
            case !is_file("$rootDirectory/$fileParts[0]/$format/$fsSourceFilename"); // no break
            case !FileSystemHelper::fileExists("$rootDirectory/$fileParts[0]/$format", $fsSourceFilename, true, $this->winFSCharset); // no break
                return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getRelativeUrl($data, $format = null)
    {
        $this->checkFileData($data, $format);

        list($folder, $filename) = explode('/', $data);
        $encodedFilename = rawurlencode($filename);
        $rootWebPath = $this->getRootWebPath();

        if ($format === null) {
            return "$rootWebPath/$folder/$encodedFilename";
        } else {
            return "$rootWebPath/$folder/$format/$encodedFilename";
        }
    }

    /**
     * @inheritdoc
     */
    public function getReadFilePath($data, $format = null)
    {
        $this->checkFileData($data, $format);

        list($folder, $filename) = explode('/', $data);
        $rootDirectory = $this->getRootDirectory();
        $fsFilename = FileSystemHelper::encodeFilename($filename, $this->winFSCharset);

        if ($format === null) {
            return "$rootDirectory/$folder/$fsFilename";
        } else {
            return "$rootDirectory/$folder/$format/$fsFilename";
        }
    }

    /**
     * @inheritdoc
     */
    public function getFormatList($data)
    {
        $this->checkFileData($data);

        list($folder, $filename) = explode('/', $data);
        $rootDirectory = $this->getRootDirectory();
        $formatDirs = FileSystemHelper::findDirectories("$rootDirectory/$folder", [
            'recursive' => false,
        ]);

        $fsFilename = FileSystemHelper::encodeFilename($filename, $this->winFSCharset);
        $result = [];
        foreach ($formatDirs as $formatDir) {
            if (is_file("$formatDir/$fsFilename") && FileSystemHelper::fileExists($formatDir, $fsFilename, true, $this->winFSCharset)) {
                $result[] = FileSystemHelper::basename($formatDir);
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getAllFiles()
    {
        $rootDirectory = $this->getRootDirectory();
        $fileDirs = FileSystemHelper::findDirectories($rootDirectory, [
            'recursive' => false,
        ]);

        $result = [];
        foreach ($fileDirs as $fileDir) {
            $files = FileSystemHelper::findFiles($fileDir, [
                'recursive' => false,
            ]);
            $folder = FileSystemHelper::basename($fileDir);

            foreach ($files as $fsFile) {
                $file = FileSystemHelper::decodeFilename($fsFile, $this->winFSCharset);
                $result[] = "$folder/" . FileSystemHelper::basename($file);
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function deleteFile($data)
    {
        if (!$this->fileExists($data)) {
            return false;
        }

        $result = true;
        foreach ($this->getFormatList($data) as $format) {
            if (!$this->deleteFormattedFile($data, $format)) {
                $result = false;
            }
        }

        list($folder, $filename) = explode('/', $data);
        $rootDirectory = $this->getRootDirectory();
        $fsFilename = FileSystemHelper::encodeFilename($filename, $this->winFSCharset);

        if (!@unlink("$rootDirectory/$folder/$fsFilename")) {
            $result = false;
        }
        if ($result && FileSystemHelper::isEmptyDirectory("$rootDirectory/$folder")) {
            @rmdir("$rootDirectory/$folder");
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function deleteFormattedFile($data, $format)
    {
        if (!$this->fileExists($data, $format)) {
            return false;
        }

        list($folder, $filename) = explode('/', $data);
        $rootDirectory = $this->getRootDirectory();
        $fsFilename = FileSystemHelper::encodeFilename($filename, $this->winFSCharset);

        if ($result = @unlink("$rootDirectory/$folder/$format/$fsFilename")) {
            if (FileSystemHelper::isEmptyDirectory("$rootDirectory/$folder/$format")) {
                @rmdir("$rootDirectory/$folder/$format");
            }
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getBaseName($data, $format = null)
    {
        $this->checkFileData($data, $format);
        return FileSystemHelper::basename($data);
    }

    /**
     * @inheritdoc
     */
    public function getFileName($data, $format = null)
    {
        $this->checkFileData($data, $format);
        return FileSystemHelper::filename($data);
    }

    /**
     * @inheritdoc
     */
    public function getExtension($data, $format = null)
    {
        $this->checkFileData($data, $format);
        return FileSystemHelper::extension($data);
    }

    /**
     * Creates and returns full path to directory which will be used to store files.
     * This method will convert `$dir` property.
     * @return string path to directory.
     * @throws InvalidConfigException if cannot create directory.
     */
    public function getRootDirectory()
    {
        $dir = Yii::getAlias(str_replace('{context}', $this->context->name, $this->dir));
        $dir = rtrim($dir, '\/');

        if (!FileSystemHelper::createDirectory($dir, $this->makeDirMode)) {
            throw new InvalidConfigException("Cannot create directory: $dir");
        }

        return $dir;
    }

    /**
     * Creates and returns web path to directory which will be used to getting urls.
     * This method will convert `$webPath` property.
     * @return string path to directory.
     */
    public function getRootWebPath()
    {
        $url = Yii::getAlias(str_replace('{context}', $this->context->name, $this->webPath));
        return rtrim($url, '\/');
    }

    /**
     * Creates and returns folder for concrete file.
     * @param string $filename base name of file for which must be returned folder.
     * @return string folder name of the directory for the `$filename`.
     * It is NOT full path to directory, only last part (folder name).
     */
    public function getFileFolder($filename)
    {
        $rootDirectory = rtrim($this->getRootDirectory(), '\/');
        if (false === $rootDirChilds = @scandir($rootDirectory, SCANDIR_SORT_NONE)) {
            throw new InvalidConfigException("Unable to scan directory: $rootDirectory");
        }
        $rootDirChilds = array_filter($rootDirChilds, function ($dir) {
            return $dir !== '.' && $dir !== '..';
        });
        if (count($rootDirChilds) > 1) {
            shuffle($rootDirChilds);
        }

        $fsFilename = FileSystemHelper::encodeFilename($filename, $this->winFSCharset);
        foreach ($rootDirChilds as $dir) {
            switch (true) {
                case !is_dir("$rootDirectory/$dir"); // no break
                case FileSystemHelper::fileExists("$rootDirectory/$dir", $fsFilename, false, $this->winFSCharset); // no break
                case FileSystemHelper::directoryFilesCount("$rootDirectory/$dir") > $this->maxSubdirFilesCount:
                    $dir = false;
                    break;
                default:
                    break 2;
            }
        }

        // generate new subdir name
        while ($dir === false) {
            $dir = $this->generateRandomString($this->subdirsLength);
            $dirpath = "$rootDirectory/$dir";
            if (FileSystemHelper::fileExists($rootDirectory, $dir, false, false)) {
                $dir = false;
            } elseif (!FileSystemHelper::createDirectory($dirpath, $this->makeDirMode)) {
                throw new InvalidConfigException("Cannot create directory: $dirpath");
            }
        }

        return $dir;
    }

    /**
     * Generates filename for new file according to storage's configuration.
     * @param string|null $originFilename name of origin file.
     * If `saveOriginNames` is true the storage will analyse and use this property for new filename.
     * Otherwise the storage will analyse and use only extension from this property.
     * @return string generated new filename.
     */
    public function createNewFilename($originFilename = null)
    {
        if ($originFilename !== null) {
            $extension = FileSystemHelper::extension($originFilename);
            $extension = FileSystemHelper::normalizeFilename($extension, $this->winFSCharset);
            if (!$this->isValidExtension($extension)) {
                $extension = null;
            }

            if ($this->saveOriginNames) {
                $filename = FileSystemHelper::filename($originFilename);
                $filename = FileSystemHelper::normalizeFilename($filename, $this->winFSCharset);
                if (!$this->isValidFileName($filename)) {
                    $filename = null;
                }
            } else {
                $filename = null;
            }

            $charset = Yii::$app->charset;
            if ($filename !== null) {
                if (mb_strlen($extension === null ? $filename : "$filename.$extension", $charset) > $this->maxLength) {
                    $filename = null;
                }
            }
            if ($filename === null && $extension !== null) {
                if ($this->fileNamesLength + 1/*.*/ + mb_strlen($extension, $charset) > $this->maxLength) {
                    $extension = null;
                }
            }
        }

        if (!isset($filename)) {
            $filename = $this->generateRandomString($this->fileNamesLength);
        }
        return isset($extension) ? "$filename.$extension" : $filename;
    }

    /**
     * Validates file name.
     * @param string $filename the filename that must be validated.
     * @param boolean $validateEncoded whether method must validate encoded in local file system charset file name.
     * This param internally is used by this method.
     * @param boolean $validateEncoded whether method must validate encoded in local file system charset file name.
     * This param internally is used by this method.
     * @return boolean whether this filename can be used for saving new file.
     */
    protected function isValidFileName($filename, $validateEncoded = true)
    {
        if (!is_string($filename) || in_array($filename, ['', '.', '..'], true)) {
            return false;
        }
        if ($this->containsSpecialChar($filename)) {
            return false;
        }

        $charset = Yii::$app->charset;
        foreach ($this->disallowedExtensions as $disallowedExt) {
            if (StringHelper::endsWith($filename, ".$disallowedExt", false)) {
                return false;
            } elseif (mb_strripos($filename, ".$disallowedExt.", 0, $charset) !== false) {
                return false;
            }
        }

        if ($validateEncoded) {
            $encodedFilename = FileSystemHelper::encodeFilename($filename, $this->winFSCharset);
            return $this->isValidFileName($encodedFilename, false);
        }

        return true;
    }

    /**
     * Validates file extension.
     * @param string $extension the extension that must be validated.
     * @param boolean $validateEncoded whether method must validate encoded in local file system charset extension.
     * This param internally is used by this method.
     * @return boolean whether this extension can be used for saving new file.
     */
    protected function isValidExtension($extension, $validateEncoded = true)
    {
        $charset = Yii::$app->charset;
        if (!is_string($extension) || $extension === '' || mb_strpos($extension, '.', 0, $charset) !== false) {
            return false;
        }
        if (!preg_match($this->extensionRegular, $extension)) {
            return false;
        }
        if ($this->containsSpecialChar($extension)) {
            return false;
        }

        $extensionLoweredCase = mb_strtolower($extension, $charset);
        foreach ($this->disallowedExtensions as $disallowedExt) {
            if (mb_strtolower($disallowedExt, $charset) === $extensionLoweredCase) {
                return false;
            }
        }

        if ($validateEncoded) {
            $encodedExtension = FileSystemHelper::encodeFilename($extension, $this->winFSCharset);
            return $this->isValidExtension($encodedExtension, false);
        }

        return true;
    }

    /**
     * Checks input string for special chars.
     * @param string $str
     * @return boolean whether input string contains special chars or not.
     * @see $specialChars
     */
    protected function containsSpecialChar($str)
    {
        $chars = $this->specialChars;
        for ($i = 0, $length = strlen($chars); $i < $length; ++$i) {
            if (strpos($str, $chars[$i]) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generates new random string that will be used in subdirectories and files names.
     * @param integer $length the length of new generated string.
     * @return string new generated string.
     */
    protected function generateRandomString($length = 32)
    {
        return Yii::$app->security->generateRandomString($length);
    }
}
