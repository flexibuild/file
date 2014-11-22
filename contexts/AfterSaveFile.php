<?php

namespace flexibuild\file\contexts;

use yii\base\Event;

/**
 * Event that will be triggered after saving file.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class AfterSaveFile extends Event
{
    /**
     * @var string full path to source file that will be copied and saved.
     */
    public $sourceFilePath;

    /**
     * @var string origin name of file.
     */
    public $originFilename;

    /**
     * @var string data that can be used for manipulating with file. You can change this value.
     */
    public $fileData;
}
