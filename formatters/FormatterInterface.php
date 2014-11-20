<?php

namespace flexibuild\file\formatters;

/**
 * Formatter interface describes base methods that must be implemented in formatter classes.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
interface FormatterInterface
{
    /**
     * Converts file to the format that current formatter implements.
     * @param string $readFilePath path to file that must be read.
     * @return string path to temp file (formatted file version).
     * Note this file will be deleted by other objects that use this method.
     * @throws \Exception if file cannot be formatted.
     */
    public function format($readFilePath);
}
