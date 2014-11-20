<?php

namespace flexibuild\file\storages;

use yii\base\Event;

/**
 * Event raised in [[AbstractStorage::deleteFile()]].
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class DeleteFileEvent extends Event
{
    /**
     * @var string identifier data of file.
     */
    public $fileInfo;

    /**
     * @var bool result of delete process. Filled only for after delete event.
     */
    public $result;
}
