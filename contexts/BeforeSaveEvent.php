<?php

namespace flexibuild\file\contexts;

use yii\base\Event;

/**
 * Event that will be triggered before saving.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class BeforeSaveEvent extends Event
{
    /**
     * @var string full path to source file that will be copied and saved.
     * You can change this param and this value will be used.
     * If you change this value don't forget set `$unlinkAfterSave` to false if your file must not be deleted.
     */
    public $sourceFilePath;

    /**
     * @var string origin name of file. You can chage this param and this value will be used.
     */
    public $originFilename;

    /**
     * @var boolean whether file must be unlinked after saving process.
     * This value keeps value whether file in `$sourceFilePath` will be removed.
     * This value can be changed.
     */
    public $unlinkAfterSave = false;

    /**
     * @var boolean whether need to continue saving process. Change this value if need.
     */
    public $valid = true;
}
