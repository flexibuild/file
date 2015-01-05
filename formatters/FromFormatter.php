<?php

namespace flexibuild\file\formatters;

use Yii;
use yii\base\InvalidConfigException;

/**
 * This formatter can be used for creating formatted version of file 
 * from another formatted version.
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class FromFormatter extends Formatter
{
    /**
     * @var string required string format name from which there is necessary to create formatted version of file.
     */
    public $from;

    /**
     * @var mixed required formatter configuration.
     * It is can be an instance of [[FormatterInterface]] or configuration for creating its.
     */
    public $formatter;

    /**
     * @var boolean value that indicates whether the formatter in process.
     * It is need for preventing cycles in formatters hierarchy.
     */
    private $_inProcess = false;

    /**
     * @inheritdoc
     * @param boolean $isFormatPath whether `$readFilePath` is the path to formatted as [[self::$from]] version of file.
     * By default is false, meaning the formatter must use context for getting formatted version.
     * True value used in [[\flexibuild\file\File::generateFormatInternal()]].
     * @throws InvalidConfigException if required `$from` & `$formatter` params are incorrect.
     */
    public function format($readFilePath, $isFormatPath = false)
    {
        if ($this->_inProcess) {
            throw new InvalidConfigException("Formatter '$this->name' of '{$this->context->name}' context has a cycle in own formatters hierarchy.");
        }
        if ($this->from === null) {
            throw new InvalidConfigException('Param $from is required for ' . get_class($this) . '.');
        }

        $this->_inProcess = true;
        try {
            if (!$isFormatPath) {
                $formatter = $this->context->getFormatter($this->from);
                $readFilePath = $formatter->format($readFilePath);
                $unlinkFilePath = $readFilePath;
            }

            $this->initFormatter();
            $formatter = $this->formatter;
            /* @var $formatter FormatterInterface */

            $result = $formatter->format($readFilePath);
        } catch (\Exception $ex) {
            $this->_inProcess = false;
            throw $ex;
        }
        $this->_inProcess = false;

        if (isset($unlinkFilePath) && !@unlink($unlinkFilePath)) {
            Yii::warning("Cannot unlink tmp file: $unlinkFilePath", __METHOD__);
        }
        return $result;
    }

    /**
     * Initializes formatter by an instance of [[FormatterInterface]]
     * @throws InvalidConfigException if `$formatter` param has incorrect format.
     */
    protected function initFormatter()
    {
        if ($this->formatter === null) {
            throw new InvalidConfigException('Param $formatter is required for ' . get_class($this) . '.');
        }
        $this->formatter = $this->context->instantiateFormatter($this->formatter);
    }
}
