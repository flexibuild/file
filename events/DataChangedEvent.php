<?php

namespace flexibuild\file\events;

/**
 * This event class used for events triggerred by File object when it object's data was changed.
 * @see [[File::setData()]] method.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class DataChangedEvent extends FileEvent
{
    /**
     * @var string|null old data value.
     */
    public $oldFileData;

    /**
     * @var string|null new file data.
     */
    public $newFileData;
}
