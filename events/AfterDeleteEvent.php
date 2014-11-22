<?php

namespace flexibuild\file\events;

/**
 * Event that will be triggered after file deleting.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class AfterDeleteEvent extends FileEvent
{
    /**
     * @var string|null string name of format which was deleted.
     * Null meaning source file was deleted.
     */
    public $format;
}
