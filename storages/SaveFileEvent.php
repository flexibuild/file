<?php

namespace flexibuild\file\storages;

use yii\base\Event;

/**
 * Event raised in [[AbstractStorage::saveFile()]] and [[AbstractStorage::saveContent()]].
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class SaveFileEvent extends Event
{
    /**
     * @var string path to temp file. Filled only if [[saveFile()]]
     * of a storage was called.
     */
    public $tmpFilepath;

    /**
     * @var string file content.
     */
    public $content;

    /**
     * @var string origin filename. Filled if origin filename was passed in
     * save method of a storage.
     */
    public $originFilename;

    /**
     * @var string identifier data of saved method. Filled only for after save event.
     */
    public $resultInfo;
}
