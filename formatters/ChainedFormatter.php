<?php

namespace flexibuild\file\formatters;

/**
 * Formatter that applies a set of other formatters.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class ChainedFormatter extends Formatter
{
    /**
     * @var array array of formatters that must be applied.
     * Each element is an instance of [[FormatterInterface]] or config for creating it.
     */
    public $formatters;

    /**
     * @inheritdoc
     */
    public function format($readFilePath)
    {
        $this->initFormatters();
        foreach ($this->formatters as $formatter) {
            /* @var $formatter FormatterInterface */
            $readFilePath = $formatter->format($readFilePath);
        }
        return $readFilePath;
    }

    /**
     * Initializes formatters by an instances of [[FormatterInterface]]
     * @throws InvalidConfigException if `$formatters` param has incorrect format.
     */
    protected function initFormatters()
    {
        if (!is_array($this->formatters)) {
            throw new InvalidConfigException('Param $formatters is required for ' . get_class($this) . '.');
        }
        foreach ($this->formatters as $key => $formatter) {
            $this->formatters[$key] = $this->context->instantiateFormatter($formatter);
        }
    }
}
