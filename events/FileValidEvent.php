<?php

namespace flexibuild\file\events;

/**
 * Event that triggers before saving and deleting file.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class FileValidEvent extends FileEvent
{
    /**
     * @var boolean whether the file is in valid status. Defaults to true.
     * A model is in valid status if it passes validations or certain checks.
     */
    public $valid = true;
}
