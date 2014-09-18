<?php

namespace flexibuild\file\helpers;

/**
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class CharsetHelper
{
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
