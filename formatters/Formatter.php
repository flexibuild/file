<?php

namespace flexibuild\file\formatters;

use yii\base\Object;

use flexibuild\file\contexts\Context;

/**
 * Base formatter class that implements [[FormatterInterface]] and has helpful methods.
 * See documentation of [[FormatterInterface]] methods for more info.
 * @see FormatterInterface
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
abstract class Formatter extends Object implements FormatterInterface
{
    /**
     * @var Context the owner of this formatter.
     */
    public $context;

    /**
     * @var string name of this formatter.
     */
    public $name;

    /**
     * @inheritdoc
     */
    abstract public function format($readFilePath);
}
