<?php

namespace flexibuild\file\events;

/**
 * Event that will be triggered before file deleting.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class BeforeDeleteEvent extends FileValidEvent
{
    /**
     * @var string|null string name of format which will be deleted.
     * Null meaning source file will be deleted.
     */
    public $format;
}
