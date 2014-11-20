<?php

namespace flexibuild\file\events;

use yii\base\Event;

use flexibuild\file\File;
use flexibuild\file\FileHandler;

/**
 * The base class for all file events.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 * 
 * @property FileHandler $sender
 */
class FileEvent extends Event
{
    /**
     * @var File the file that was changed.
     */
    public $file;
}
