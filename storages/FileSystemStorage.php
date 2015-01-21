<?php

namespace flexibuild\file\storages;

use Yii;
use yii\base\InvalidConfigException;
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
     * @var string the subdirectory for saving formatted versions of files.
     */
    public $formatsSubdir = 'formats';

    /**
     * @var int mode for creating new directories.
     */
    public $makeDirMode = 0777;

    /**
     * @var int mode for creating new files.
     */
    public $createFileMode = 0666;

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
     * If you want to change this property value, don't forget to change `$maxLength`
     * value for keeping file data in your database. Now these params allows keep values
     * in column typed as VARCHAR(255).
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
        'php3',
        'php4',
        'php5',
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
     * Default value is 250 for keeping file data in databases in columns
     * typed as VARCHAR(255). 5 chars reserved for subdirectory and one slash.
     */
    public $maxLength = 250;

    /**
     * @var string this suffix will be added to all filenames of formatted files.
     * Be carefully, if you want to change this property.
     * It must not contain special or non-latin chars, but must be an unique string that differs from format names.
     * By default '-format', because '-' symbol cannot be in format names.
     */
    public $formatSuffix = '-format';

    /**
     * @inheritdoc
     */
    public function saveFile($content, $originFilename = null)
    {
        $filename = $this->createNewFilename($originFilename);
        $fsFilename = FileSystemHelper::encodeFilename($filename);
        $rootDir = $this->getRootDirectory();
        $folder = $this->getFileFolder($filename);

        if (false === @file_put_contents("$rootDir/$folder/$fsFilename", $content)) {
            Yii::warning("Cannot save file: $rootDir/$folder/$filename", __METHOD__);
            return false;
        }
        if (!@chmod("$rootDir/$folder/$fsFilename", $this->createFileMode)) {
            Yii::warning('Cannot change file permissions to 0'.(base_convert($this->createFileMode, 10, 8))." for file: $rootDir/$folder/$filename", __METHOD__);
        }
        return "$folder/$filename";
    }

    /**
     * @inheritdoc
     */
    public function saveFileByCopying($sourceFilePath, $originFilename = null)
    {
        $filename = $this->createNewFilename($originFilename);
        $fsFilename = FileSystemHelper::encodeFilename($filename);
        $rootDir = $this->getRootDirectory();
        $folder = $this->getFileFolder($filename);

        if (!@copy($sourceFilePath, "$rootDir/$folder/$fsFilename")) {
            Yii::warning("Cannot copy file '$sourceFilePath' to '$rootDir/$folder/$filename'.", __METHOD__);
            return false;
        }
        if (!@chmod("$rootDir/$folder/$fsFilename", $this->createFileMode)) {
            Yii::warning('Cannot change file permissions to 0'.(base_convert($this->createFileMode, 10, 8))." for file: $rootDir/$folder/$filename", __METHOD__);
        }
        return "$folder/$filename";
    }

    /**
     * @inheritdoc
     */
    public function saveFormattedFile($data, $content, $formatName, $extension = false)
    {
        $fsFormatPath = $this->_getFormatFSFilePath($data, $formatName, $extension);
        if (false === @file_put_contents($fsFormatPath, $content)) {
            Yii::warning("Cannot save formatted as '$formatName' version of file '$data' in '{$this->context->name}' context.");
            return false;
        }
        if (!@chmod($fsFormatPath, $this->createFileMode)) {
            $formatPath = FileSystemHelper::decodeFilename($fsFormatPath);
            Yii::warning('Cannot change file permissions to 0'.(base_convert($this->createFileMode, 10, 8))." for file: $formatPath", __METHOD__);
        }
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function saveFormattedFileByCopying($data, $sourceFilePath, $formatName)
    {
        $decodedFilePath = FileSystemHelper::decodeFilename($sourceFilePath);
        $extension = $decodedFilePath === false ? false : FileSystemHelper::extension($decodedFilePath);

        $fsFormatPath = $this->_getFormatFSFilePath($data, $formatName, $extension);
        if (!@copy($sourceFilePath, $fsFormatPath)) {
            Yii::warning("Cannot copy formatted as '$formatName' version of file ($sourceFilePath) for '$data' file in '{$this->context->name}' context.");
            return false;
        }
        if (!@chmod($fsFormatPath, $this->createFileMode)) {
            $formatPath = FileSystemHelper::decodeFilename($fsFormatPath);
            Yii::warning('Cannot change file permissions to 0'.(base_convert($this->createFileMode, 10, 8))." for file: $formatPath", __METHOD__);
        }
        return $data;
    }

    /**
     * Creates directory for formatted version of file and returns full path to formatted version of file.
     * Note returned file path will be in local file system character set.
     * @param string $data data of source file.
     * @param string $formatName the format name.
     * @param string|false $extension the extension of formatted version of file.
     * False meaning file has not extension.
     * @return string full path to formatted version of file.
     * @throws InvalidParamException if file with `$data` does not exist.
     * @throws InvalidConfigException if cannot create directory for format.
     */
    private function _getFormatFSFilePath($data, $formatName, $extension)
    {
        $this->checkFileData($data);

        list($folder, $basename) = explode('/', $data);
        $fsBasename = FileSystemHelper::encodeFilename($basename);

        $dirname = $this->getRootDirectory() . "/$folder/$this->formatsSubdir";
        $fsFormatDir = "$dirname/$fsBasename";

        if (!FileSystemHelper::createDirectory($fsFormatDir, $this->makeDirMode)) {
            throw new InvalidConfigException("Cannot create directory: $dirname/$basename");
        }
        return "$fsFormatDir/$formatName{$this->formatSuffix}" .
            ($extension === false ? '' : ".$extension");
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
        $fsSourceFilename = FileSystemHelper::encodeFilename($fileParts[1]);

        switch (true) {
            case $fsSourceFilename === false; // no break
            case !preg_match('/^[a-z0-9\-\_]+$/i', $fileParts[0]); // no break
            case in_array($fsSourceFilename, ['', '.', '..'], true); // no break
            case $this->containsSpecialChar($fsSourceFilename); // no  break
            case !is_dir("$rootDirectory/$fileParts[0]"); // no break
            case !FileSystemHelper::fileExists($rootDirectory, $fileParts[0], true); // no break
            case !is_file("$rootDirectory/$fileParts[0]/$fsSourceFilename"); // no break
            case !FileSystemHelper::fileExists("$rootDirectory/$fileParts[0]", $fsSourceFilename, true); // no break
                return false;

            case $format === null:
                return true;

            case !is_scalar($format); // no break
            case !preg_match('/^[0-9a-z\_]+$/i', $format); // no break
            case !($fsFormatsDir = "$rootDirectory/$fileParts[0]/$this->formatsSubdir"); // no break
            case !is_dir("$fsFormatsDir/$fsSourceFilename"); // no break
            case !FileSystemHelper::fileExists($fsFormatsDir, $fsSourceFilename, true); // no break
                return false;
        }

        return $this->_findFormatBasename("$fsFormatsDir/$fsSourceFilename", $format) !== false;
    }

    /**
     * Searches basename of formatted version of file.
     * @param string $dir full path to directory where formatted file must be searched.
     * This param must be in local file system charset.
     * @param string $format the name of format.
     * @return string|boolean basename of formatted version of file in Yii app charset or false if file does not exist.
     */
    private function _findFormatBasename($dir, $format)
    {
        if (!is_dir($dir)) {
            return false;
        }
        $files = FileSystemHelper::findFiles($dir, [
            'only' => ["/$format{$this->formatSuffix}*"],
            'caseSensitive' => true,
            'recursive' => false,
        ]);

        if (count($files) !== 1 || !isset($files[0])) {
            return false;
        }
        if (false === $decodedFilename = FileSystemHelper::decodeFilename($files[0])) {
            return false;
        }
        return FileSystemHelper::basename($decodedFilename);
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
        }

        $rootDir = $this->getRootDirectory();
        $fsFilename = FileSystemHelper::encodeFilename($filename);
        $formatFilename = $this->_findFormatBasename("$rootDir/$folder/$this->formatsSubdir/$fsFilename", $format);
        return "$rootWebPath/$folder/$this->formatsSubdir/$encodedFilename/" . rawurlencode($formatFilename);
    }

    /**
     * @inheritdoc
     */
    public function getReadFilePath($data, $format = null)
    {
        $this->checkFileData($data, $format);

        list($folder, $filename) = explode('/', $data);
        $rootDirectory = $this->getRootDirectory();
        $fsFilename = FileSystemHelper::encodeFilename($filename);

        if ($format === null) {
            return "$rootDirectory/$folder/$fsFilename";
        }

        $formatFilename = $this->_findFormatBasename("$rootDirectory/$folder/$this->formatsSubdir/$fsFilename", $format);
        return "$rootDirectory/$folder/$this->formatsSubdir/$fsFilename/" . 
            FileSystemHelper::encodeFilename($formatFilename);
    }

    /**
     * @inheritdoc
     */
    public function getFormatList($data)
    {
        $this->checkFileData($data);

        list($folder, $filename) = explode('/', $data);
        $rootDirectory = $this->getRootDirectory();
        $fsFilename = FileSystemHelper::encodeFilename($filename);

        $files = FileSystemHelper::findFiles("$rootDirectory/$folder/$this->formatsSubdir/$fsFilename", [
            'only' => ["/*{$this->formatSuffix}*"],
            'caseSensitive' => true,
            'recursive' => false,
        ]);

        $result = [];
        foreach ($files as $formatFilepath) {
            $decodedFormatFilepath = FileSystemHelper::decodeFilename($formatFilepath);
            $formatBasename = FileSystemHelper::basename($decodedFormatFilepath);
            if (preg_match('/^([0-9a-z\_]+)' . preg_quote($this->formatSuffix, '/') . '.*$/i', $formatBasename, $matches)) {
                $result[] = $matches[1];
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
                $file = FileSystemHelper::decodeFilename($fsFile);
                if ($file !== false) {
                    $result[] = "$folder/" . FileSystemHelper::basename($file);
                }
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
        $fsFilename = FileSystemHelper::encodeFilename($filename);

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
        $fsFilename = FileSystemHelper::encodeFilename($filename);

        if (false === $formatBasename = $this->_findFormatBasename("$rootDirectory/$folder/$this->formatsSubdir/$fsFilename", $format)) {
            return false;
        }
        $fsFormatBasename = FileSystemHelper::encodeFilename($formatBasename);

        if ($result = @unlink("$rootDirectory/$folder/$this->formatsSubdir/$fsFilename/$fsFormatBasename")) {
            $clearDirs = [
                "$rootDirectory/$folder/$this->formatsSubdir/$fsFilename",
                "$rootDirectory/$folder/$this->formatsSubdir",
            ];
            foreach ($clearDirs as $dir) {
                if (!FileSystemHelper::isEmptyDirectory($dir)) {
                    break;
                }
                if (!@rmdir($dir)) {
                    break;
                }
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
        if ($format === null) {
            return FileSystemHelper::basename($data);
        }

        list($folder, $filename) = explode('/', $data);
        $rootDir = $this->getRootDirectory();
        $fsFilename = FileSystemHelper::encodeFilename($filename);
        return $this->_findFormatBasename("$rootDir/$folder/$this->formatsSubdir/$fsFilename", $format);
    }

    /**
     * @inheritdoc
     */
    public function getFileName($data, $format = null)
    {
        $basename = $this->getBaseName($data, $format);
        return FileSystemHelper::basename($basename);
    }

    /**
     * @inheritdoc
     */
    public function getExtension($data, $format = null)
    {
        $basename = $this->getBaseName($data, $format);
        return FileSystemHelper::extension($basename);
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
        $rootDirChilds = array_filter($rootDirChilds, function ($dir) use ($rootDirectory) {
            return $dir !== '.' && $dir !== '..' && is_dir("$rootDirectory/$dir");
        });
        if (count($rootDirChilds) > 1) {
            shuffle($rootDirChilds);
        }

        $fsFilename = FileSystemHelper::encodeFilename($filename);
        $dir = false;
        foreach ($rootDirChilds as $dir) {
            switch (true) {
                case FileSystemHelper::fileExists("$rootDirectory/$dir", $fsFilename, false); // no break
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
            if (FileSystemHelper::fileExists($rootDirectory, $dir, false)) {
                $dir = false;
            } elseif (!FileSystemHelper::createDirectory($dirpath, $this->makeDirMode, false)) {
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
            $extension = FileSystemHelper::normalizeFilename($extension);
            if (!$this->isValidExtension($extension)) {
                $extension = null;
            }

            if ($this->saveOriginNames) {
                $filename = FileSystemHelper::filename($originFilename);
                $filename = FileSystemHelper::normalizeFilename($filename);
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
            $encodedFilename = FileSystemHelper::encodeFilename($filename);
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
            $encodedExtension = FileSystemHelper::encodeFilename($extension);
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
