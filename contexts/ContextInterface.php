<?php

namespace flexibuild\file\contexts;

/**
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
interface ContextInterface
{
    /**
     * Gets context's file storage object.
     * @return \flexibuild\file\storages\AbstractStorage
     */
    public function getStorage();

    /**
     * Gets context's file handler.
     * @return \flexibuild\file\handlers\HandlerInterface
     */
    public function getHandler();
}
