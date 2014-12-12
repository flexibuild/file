<?php

namespace flexibuild\file\helpers;

use Yii;
use yii\base\InvalidParamException;
use yii\base\InvalidConfigException;
use yii\helpers\BaseFileHelper;

/**
 * Helper implements some methods for parsing file path parts.
 * Php engine has the same native methods but they does not work with UTF8 names in some environments.
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class BaseFileSystemHelper extends BaseFileHelper
{
    /**
     * @var string|null path to directory which can be used for storing temp files.
     * You can use Yii aliases.
     * Null meaning `sys_get_temp_dir()` will be used.
     */
    static $tempDir = '@runtime/flexibuild/temp';

    /**
     * Method works like php `basename` function. Rewrited because php's basename
     * works incorrectly with some utf8 names.
     * @param string $filepath input file path.
     * File path must have Yii application charset.
     * @return string basename of input file path.
     */
    public static function basename($filepath)
    {
        $filepath = str_replace('\\', '/', $filepath);
        $filepath = rtrim($filepath, '/');
        $charset = Yii::$app->charset;

        if (false !== $pos = mb_strrpos($filepath, '/', 0, $charset)) {
            $length = mb_strlen($filepath, $charset);
            return mb_substr($filepath, $pos + 1, $length - $pos - 1, $charset);
        }
        return $filepath;
    }

    /**
     * Method works like php `dirname` function. Rewrited because php's dirname
     * works incorrectly with some utf8 names.
     * @param string $filepath input file path.
     * File path must have in Yii application charset.
     * @return string dirname of input file path.
     */
    public static function dirname($filepath)
    {
        $filepath = rtrim($filepath, '\/');
        $charset = Yii::$app->charset;
        $pos = mb_strrpos(str_replace('\\', '/', $filepath), '/', 0, $charset);
        if ($pos !== false) {
            return rtrim(mb_substr($filepath, 0, $pos, $charset), '\/');
        }
        return '.';
    }

    /**
     * Method works like php `pathinfo` function with PATHINFO_FILENAME parameter.
     * Rewrited because php's pathinfo works incorrectly with some utf8 names.
     * @param string $filepath input file path.
     * File path must have Yii application charset.
     * @return string filename of input file path.
     */
    public static function filename($filepath)
    {
        $basename = static::basename($filepath);
        $charset = Yii::$app->charset;
        if (false !== $pos = mb_strrpos($basename, '.', 0, $charset)) {
            return mb_substr($basename, 0, $pos, $charset);
        }
        return $basename;
    }

    /**
     * Method works like php `pathinfo` function with PATHINFO_EXTENSION parameter.
     * Rewrited because php's pathinfo works incorrectly with some utf8 names.
     * @param string $filepath input file path.
     * File path must have Yii application charset.
     * @return string|null extension of input file path. Null meaning file path
     * is without extension.
     */
    public static function extension($filepath)
    {
        $basename = static::basename($filepath);
        $charset = Yii::$app->charset;
        if (false !== $pos = mb_strrpos($basename, '.', 0, $charset)) {
            $length = mb_strlen($basename, $charset);
            if ($pos === $length - 1) {
                return '';
            }
            return mb_substr($basename, $pos + 1, $length - $pos - 1, $charset);
        }
        return null;
    }

    /**
     * Method works like php `pathinfo` function.
     * Rewrited because php's pathinfo works incorrectly with some utf8 names.
     * @param string $filepath input file path.
     * File path must have Yii application charset.
     * @param int $options options of php `pathinfo` function. Null meaning array
     * with oll options will be returned.
     * @return string|array String file path option or array of all options if 
     * param `$options` is null.
     */
    public static function pathinfo($filepath, $options = null)
    {
        switch (true) {
            case $options === null:
                $result = [
                    'dirname' => static::dirname($filepath),
                    'basename' => static::basename($filepath),
                    'extension' => static::extension($filepath),
                    'filename' => static::filename($filepath),
                ];
                if ($result['extension'] === null) {
                    unset($result['extension']);
                }
                return $result;

            case $options & PATHINFO_DIRNAME:
                return static::dirname($filepath);
            case $options & PATHINFO_BASENAME:
                return static::basename($filepath);
            case $options & PATHINFO_EXTENSION:
                $extension = static::extension($filepath);
                return $extension === null ? '' : $extension;
            case $options & PATHINFO_FILENAME:
                return static::filename($filepath);

            default:
                return '';
        }
    }

    /**
     * Checks whether a file or directory exists.
     * @param string $dir the dir where file must be searched.
     * The method does not use `$caseSensitive` value while working with this directory.
     * This directory will be used according to local file system case sensitivity.
     * The directory must have local file system charset.
     * @param string $filename name of file that must be checked.
     * Filename must have local file system charset.
     * @param bool|null $caseSensitive boolean whether method must use case sensitive name comparing.
     * Null meaning comparing will be done according to local file system case sensitivity.
     * This param does not affect to working with `$dir` param.
     * @param mixed $winFSCharset charset of local file system.
     * Null meaning the helper will try to detect filesystem charset.
     * False meaning the helper will not convert filename.
     * @return boolean whether the file exists.
     */
    public static function fileExists($dir, $filename, $caseSensitive = null, $winFSCharset = null)
    {
        if (!is_dir($dir)) {
            return false;
        }
        $dir = rtrim($dir, '\/');

        if (!$caseSensitive) { // false or null
            if (file_exists($dir . DIRECTORY_SEPARATOR . $filename)) {
                return true;
            } elseif ($caseSensitive === null || static::isWindowsOS()) {
                return false;
            }

            $charset = static::detectWindowsFSCharset($winFSCharset, true) ?: Yii::$app->charset;
            $filename = mb_strtolower($filename, $charset);
        }
        $filename = rtrim($filename, '\/');

        if (!($handle = @opendir($dir))) {
            return false;
        }
        while (false !== $dirFile = readdir($handle)) {
            if ($dirFile === '.' || $dirFile === '..') {
                continue;
            }
            if ($caseSensitive) {
                if ($dirFile === $filename) {
                    closedir($handle);
                    return true;
                }
            } else {
                /* @var $charset string charset */ // will isset here
                if (mb_strtolower($dirFile, $charset) === $filename) {
                    closedir($handle);
                    return true;
                }
            }
        }
        closedir($handle);
        return false;
    }

    /**
     * Determines the charset of the current file system.
     * @staticvar string|null $result cached result.
     * @return string|null charset for local file system. Null will be
     * returned if determining was failed.
     */
    public static function getFileSystemCharset()
    {
        static $result = false;
        if ($result !== false) {
            return $result;
        }

        if (function_exists('nl_langinfo') && defined('CODESET')) {
            $result = nl_langinfo(CODESET);
            /* OpenBSD may improperly return D_T_FMT value */
            if (isset($result) && strpos($result, '%') !== false && defined('D_T_FMT') && $result === nl_langinfo(D_T_FMT)) {
                $result = null;
            }
        }

        if (empty($result)) {
            $result = @setlocale(LC_CTYPE, '0');
            if (preg_match('/\.(.*)$/', $result, $matches)) {
                $result = $matches[1];
            }
        }
        if (empty($result)) {
            return $result = null;
        }

        /*
         * Remap some known charset issues.
         * See http://cvsweb.xfree86.org/cvsweb/xc/nls/locale.alias?rev=1.44
         */
        $intResult = (int) $result;
        if ($intResult === 646) {
            $result = 'ASCII';
        } elseif ($intResult >= 1250 && $intResult <= 1259) {
            $result = "CP$intResult";
        }

        /* FreeBSD may return charset with missing hyphen from nl_langinfo */
        $result = preg_replace('/iso8859/i', 'ISO-8859', $result);
        /* Gentoo may return ANSI_X3.4-1968 */
        if (preg_match('/^ANSI[_-]/', $result)) {
            $result = 'ASCII';
        }

        return $result;
    }

    /**
     * Method was override for using [[static::extension()]] method on parsing file extension.
     * The `$file` param must have Yii application charset.
     * @inheritdoc
     */
    public static function getMimeTypeByExtension($file, $magicFile = null)
    {
        $mimeTypes = static::loadMimeTypes($magicFile);
        $ext = static::extension($file);

        if ($ext !== '' && $ext !== null) {
            $ext = strtolower($ext);
            if (isset($mimeTypes[$ext])) {
                return $mimeTypes[$ext];
            }
        }

        return null;
    }

    /**
     * Detects Windows platform.
     * @staticvar boolean $result cached result.
     * @return bool whether current operating system is Windows or not.
     */
    public static function isWindowsOS()
    {
        static $result = null;
        if ($result === null) {
            $result = strcasecmp(substr(PHP_OS, 0, 3), 'WIN') === 0;
        }
        return $result;
    }

    /**
     * Converts filename according to local file system.
     * Returned value can be used in file_* functions.
     * This method will convert filename only if local platform is windows.
     * 
     * @param string $filename file name that must be encoded.
     * @param mixed $winFSCharset charset of local file system.
     * Null meaning the helper will try to detect filesystem charset.
     * False meaning the helper will not convert filename.
     * @return string|boolean converted filename or false on failure.
     */
    public static function encodeFilename($filename, $winFSCharset = null)
    {
        $convertCharset = static::detectWindowsFSCharset($winFSCharset);
        if ($convertCharset === false) {
            return $filename;
        }

        $fsFilename = @iconv(Yii::$app->charset, $convertCharset, $filename);
        return $fsFilename;
    }

    /**
     * Converts filename from local file system name to name which has `Yii::$app->charset` character set.
     * This method will convert filename only if local platform is windows.
     * Input file name is string returned from different file php functions, returned value will have Yii app charset.
     * 
     * @param string $fsFilename filename which has local file system charset.
     * @param mixed $winFSCharset charset of local file system.
     * Null meaning the helper will try to detect filesystem charset.
     * False meaning the helper will not convert filename.
     * @return string|boolean converted filename or false on failure.
     */
    public static function decodeFilename($fsFilename, $winFSCharset = null)
    {
        $convertCharset = static::detectWindowsFSCharset($winFSCharset, true);
        if ($convertCharset === false) {
            return $fsFilename;
        }

        $decodedFilename = @iconv($convertCharset, Yii::$app->charset, $fsFilename);
        return $decodedFilename;
    }

    /**
     * This method normalizes filename. It encodes and than decodes filename.
     * After that some filename chars may be will changed by '//TRANSLIT' and/or '//IGNORE' `iconv()` special mode.
     * 
     * @param string $filename the filename that must be normalized.
     * @param mixed $winFSCharset charset of local file system.
     * Null meaning the helper will try to detect filesystem charset.
     * False meaning the helper will not convert filename.
     * @return string|boolean normalized filename or false on failure.
     */
    public static function normalizeFilename($filename, $winFSCharset)
    {
        $fsFilename = static::encodeFilename($filename, $winFSCharset);
        return $fsFilename === false ? false : static::decodeFilename($fsFilename, $winFSCharset);
    }

    /**
     * Detects windows filesystem charset. Only for windows platforms.
     * @param mixed $winFSCharset charset of local file system.
     * Null meaning the helper will try to detect filesystem charset.
     * False meaning the helper will not convert filename.
     * @param boolean $cutSpecialModes value that indicates whether method must
     * cut special modes (like //TRANSLIT or //IGNORE) or not.
     * @return string|false string detected file system charset. False meaning converting is not needed.
     */
    protected static function detectWindowsFSCharset($winFSCharset = null, $cutSpecialModes = false)
    {
        if (!static::isWindowsOS()) {
            return false;
        }

        if ($winFSCharset === null) {
            if ($detectedCharset = static::getFileSystemCharset()) {
                return $cutSpecialModes ? $detectedCharset : "$detectedCharset//TRANSLIT//IGNORE";
            } else {
                return false;
            }
        } elseif ($winFSCharset === false) {
            return false;
        }

        if ($cutSpecialModes) {
            return str_ireplace(['//TRANSLIT', '//IGNORE'], ['', ''], $winFSCharset);
        }
        return $winFSCharset;
    }

    /**
     * @inheritdoc
     * @staticvar boolean $searchProcess variable that indicates call method is recursively called or this is the first call.
     * Fixes logic of parent method. Some file systems may has file for which is_file === false and is_dir === false.
     * For example: windows file system has it where file has "alien" character set.
     * 
     * The method returns file paths which have local file system charset.
     * The `$dir` param must be in local file system charset too.
     */
    public static function findFiles($dir, $options = [])
    {
        static $searchProcess = false;

        if (!$searchProcess) {
            $searchProcess = true;
            try {
                $result = parent::findFiles($dir, $options);
            } catch (\Exception $ex) {
                $searchProcess = false;
                throw $ex;
            }
            $searchProcess = false;
            return $result;
        }

        if (!is_dir($dir)) {
            return [];
        }
        return parent::findFiles($dir, $options);
    }

    /**
     * Returns the directories found under the specified directory and subdirectories.
     * @param string $dir the directory under which the subdirectories will be looked for.
     * The `$dir` param must be in local file system charset.
     * @param array $options options for dir searching. Valid options are:
     *
     * - filter: callback, a PHP callback that is called for each directory.
     *   The signature of the callback should be: `function ($path, &$recursive)`, where:
     *
     *   * `$path` refers the full dir path to be filtered.
     *   * `$recursive` refers to the 'recursive' otion value and can be changed for the current `$path`.
     *
     *   The callback can return one of the following values:
     *
     *   * true: the directory will be returned
     *   * false: the directory will NOT be returned
     *
     * - recursive: boolean, whether the subdirectories under the directory should also be looked for. Defaults to true.
     * @return array subdirectories found under the directory.
     * The method returns subdirectories which have local file system charset.
     * @throws InvalidParamException if the dir is invalid.
     */
    public static function findDirectories($dir, $options = [])
    {
        if (!is_dir($dir)) {
            throw new InvalidParamException('The dir argument must be a directory.');
        }

        $dir = rtrim($dir, '\/');
        $list = [];

        if (false === $handle = opendir($dir)) {
            throw new InvalidParamException("Unable to open directory: $dir");
        }
        while (false !== $file = readdir($handle)) {
            if ($file === '.' || $file === '..' || !is_dir($path = $dir . DIRECTORY_SEPARATOR . $file)) {
                continue;
            }
            $recursive = !isset($options['recursive']) || $options['recursive'];

            if (!isset($options['filter']) || static::filterDirectoryPath($options['filter'], $path, $recursive)) {
                $list[] = $path;
            }

            if ($recursive) {
                $list = array_merge($list, static::findDirectories($path, $options));
            }
        }
        closedir($handle);

        return $list;
    }

    /**
     * Checks whether directory is empty.
     * @param string $dir directory that must be checked.
     * The `$dir` param must has local file system charset.
     * @throws InvalidParamException is `$dir` is not a valid directory.
     */
    public static function isEmptyDirectory($dir)
    {
        if (!is_dir($dir)) {
            throw new InvalidParamException('The dir argument must be a directory.');
        }
        if (!($handle = opendir($dir))) {
            throw new InvalidParamException("Unable to open directory: $dir");
        }
        while (false !== $file = readdir($handle)) {
            if ($file !== '.' && $file !== '..') {
                closedir($handle);
                return false;
            }
        }
        closedir($handle);
        return true;
    }

    /**
     * Filters directory path according to [[self::findDirectories()]] 'filter' option.
     * @param callable $filter filter that must be called.
     * @param string $path path to directory to be filtered.
     * @param boolean $recursive writable param that keeps whether the directory must be recursively processed.
     * @return mixed result that `$filter` callable will return.
     */
    protected static function filterDirectoryPath($filter, $path, &$recursive)
    {
        if (is_string($filter) && count($exploded = explode('::', $filter)) > 1) {
            $filter = $exploded;
        }
        if (is_array($filter)) {
            if (is_object($filter[0])) {
                $result = $filter[0]->{$filter[1]}($path, $recursive); // object method call
            } else {
                $result = $filter[0]::{$filter[1]}($path, $recursive); // class static method call
            }
        } else { // string function name or \Closure
            $result = $filter($path, $recursive);
        }
        return $result;
    }

    /**
     * Returns the count of files under the `$dir` directory.
     * @param string $dir the directory name that must be checked.
     * The `$dir` param must has local file system charset.
     * @return integer the count of file in this dir.
     */
    public static function directoryFilesCount($dir)
    {
        $dir = rtrim($dir, '\/');
        if (false === $handle = opendir($dir)) {
            throw new InvalidParamException("Unable to open directory: $dir");
        }

        $count = 0;
        while (false !== $file = readdir($handle)) {
            if ($file !== '.' && $file !== '..' && is_file("$dir/$file")) {
                ++$count;
            }
        }
        closedir($handle);

        return $count;
    }

    /**
     * Creates and returns temp directory.
     * @return string full path to temp directory
     * @throws InvalidConfigException if cannot create dir.
     */
    public static function getTempDir()
    {
        if (static::$tempDir === null) {
            return sys_get_temp_dir();
        }
        $dir = rtrim(Yii::getAlias(static::$tempDir), '\/');
        if (!static::createDirectory($dir, 0777, true)) {
            throw new InvalidConfigException("Cannot create temporary directory: $dir");
        }
        return $dir;
    }

    /**
     * Generates new name for temporary file. This method creates directory if not exists.
     * This method use [[self::$tempDir]] param as directory for keeping temp files.
     * @param string|null $extension extension which file must have.
     * @return string full path to temp file.
     * @throws InvalidConfigException if cannot create directory.
     */
    public static function getNewTempFilename($extension = null)
    {
        $dir = static::getTempDir();
        $fileName = false;

        while ($fileName === false) {
            $fileName = Yii::$app->getSecurity()->generateRandomString(16) . 
                ($extension === null ? ".$extension" : '');
            if (static::fileExists($dir, $fileName, null, false)) {
                $fileName = false;
            }
        }

        return $fileName;
    }
}
