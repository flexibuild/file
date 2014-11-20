<?php

namespace flexibuild\file\formatters;

use yii\base\InvalidConfigException;

/**
 * Formatter that allows generate format by user-defined function.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class InlineFormatter extends Formatter
{
    /**
     * @var callable user-defined method that generates formatted version of file.
     * Method will called with two params: `function ($readFilePath, $formatter)`, where:
     * 
     * - $readFilePath string, path to temp file (formatted file version).
     * Note this file will be deleted by other objects that use this method.
     * - $formatter InlineFormatter, this formatter.
     * 
     * A value which will be returned by this method will be returned by [[self::format()]] method too.
     */
    public $format;

    /**
     * @inheritdoc
     */
    public function format($readFilePath)
    {
        if (!is_callable($this->format)) {
            throw new InvalidConfigException('Param $format of ' . get_class($this) . ' must have a callable type.');
        }
        return call_user_func($this->format, $readFilePath, $this);
    }
}
