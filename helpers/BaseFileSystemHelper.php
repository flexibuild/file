<?php

namespace flexibuild\file\helpers;

use Yii;

/**
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class BaseFileSystemHelper
{
    /**
     * Method works like php `basename` function. Rewrited because php's basename
     * works incorrectly with some utf8 names.
     * @param string $filepath input file path.
     * @return string basename of input file path.
     */
    public static function basename($filepath)
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            $filepath = str_replace(DIRECTORY_SEPARATOR, '/', $filepath);
        }
        $filepath = rtrim($filepath, '/');

        if (false !== $pos = mb_strrpos($filepath, '/', 0, Yii::$app->charset)) {
            $length = mb_strlen($filepath, Yii::$app->charset);
            return mb_substr($filepath, $pos + 1, $length - $pos - 1, Yii::$app->charset);
        }
        return $filepath;
    }

    /**
     * Method works like php `dirname` function. Rewrited because php's dirname
     * works incorrectly with some utf8 names.
     * @param string $filepath input file path.
     * @return string dirname of input file path.
     */
    public static function dirname($filepath)
    {
        $filepath = rtrim($filepath, '\/');
        $pos = mb_strrpos(str_replace('\\', '/', $filepath), '/', 0, Yii::$app->charset);
        if ($pos !== false) {
            return rtrim(mb_substr($filepath, 0, $pos, Yii::$app->charset), '\/');
        }
        return '.';
    }

    /**
     * Method works like php `pathinfo` function with PATHINFO_FILENAME parameter.
     * Rewrited because php's pathinfo works incorrectly with some utf8 names.
     * @param string $filepath input file path.
     * @return string filename of input file path.
     */
    public static function filename($filepath)
    {
        $basename = static::basename($filepath);
        if (false !== $pos = mb_strrpos($basename, '.', 0, Yii::$app->charset)) {
            return mb_substr($basename, 0, $pos, Yii::$app->charset);
        }
        return $basename;
    }

    /**
     * Method works like php `pathinfo` function with PATHINFO_EXTENSION parameter.
     * Rewrited because php's pathinfo works incorrectly with some utf8 names.
     * @param string $filepath input file path.
     * @return string|null extension of input file path. Null meaning file path
     * is without extension.
     */
    public static function extension($filepath)
    {
        $basename = static::basename($filepath);
        if (false !== $pos = mb_strrpos($basename, '.', 0, Yii::$app->charset)) {
            $length = mb_strlen($basename, Yii::$app->charset);
            if ($pos === $length - 1) {
                return '';
            }
            return mb_substr($basename, $pos + 1, $length - $pos - 1, Yii::$app->charset);
        }
        // return null;
    }

    /**
     * Method works like php `pathinfo` function.
     * Rewrited because php's pathinfo works incorrectly with some utf8 names.
     * @param string $filepath input file path.
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

        if (function_exists('nl_lnginfo') && defined('CODESET')) {
            $result = nl_langinfo(CODESET);
            /* OpenBSD may improperly return D_T_FMT value */
            if (isset($result) && strpos($result, '%') !== false && defined('D_T_FMT') && $result === nl_langinfo(D_T_FMT)) {
                $result = null;
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
}
