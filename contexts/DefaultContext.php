<?php

namespace flexibuild\file\contexts;

use Yii;
use yii\base\Component;

use flexibuild\file\storages\AbstractStorage;
use flexibuild\file\handlers\HandlerInterface;

/**
 * Contexts used in file manager for separating different file types (contexts).
 * 
 * For example, you may be want use ImageContext with local file system storage
 * for user avatars and PdfContext with some CDN storage. It is possible. 
 * You can configure your file manager and use the appropriate contexts with
 * the corresponding fields.
 * 
 * @property AbstractStorage $storage Storage for keeping files.
 * By default used file system simple storage.
 * @see \flexibuild\file\storages\filesys\SimpleStorage
 * 
 * @property HandlerInterface $handler for processing files.
 * By default used default handler.
 * @see \flexibuild\file\handlers\DefaultHandler
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class DefaultContext extends Component implements ContextInterface
{
    /**
     * @var mixed
     */
    private $_storage;

    /**
     * Gets context's file storage object.
     * @return AbstractStorage
     */
    public function getStorage()
    {
        if ($this->_storage === null) {
            $this->_storage = $this->defaultStorage();
        }
        if (!is_object($this->_storage)) {
            $this->_storage = Yii::createObject($this->_storage);
        }
        return $this->_storage;
    }

    /**
     * Sets context's file storage. You can use Yii config standarts.
     * @param mixed $storage Abstract storage or storage configuration.
     */
    public function setStorage($storage)
    {
        $this->_storage = $storage;
    }

    /**
     * Returns default context's storage configuration. This method may be
     * overrided in subclasses.
     * @return mixed default storage config. Used in `getStorage()` if storage
     * was not set.
     */
    protected function defaultStorage()
    {
        return \flexibuild\file\storages\filesys\SimpleStorage::className();
    }

    /**
     * @var mixed
     */
    private $_handler;

    /**
     * Gets context's file handler.
     * @return HandlerInterface
     */
    public function getHandler()
    {
        if ($this->_handler === null) {
            $this->_handler = DefaultHandler::className();
        }
        if (!is_object($this->_handler)) {
            $this->_handler = Yii::createObject($this->_handler);
        }
        return $this->_handler;
    }

    /**
     * Sets context's file handler. You can use Yii config standarts.
     * @param mixed $handler Handler object or handler configuration.
     */
    public function setHandler($handler)
    {
        $this->_handler = $handler;
    }

    
    /**
     * Returns default context's handler configuration. This method may be
     * overrided in subclasses.
     * @return mixed default handler config. Used in `getHandler()` if handler
     * was not set.
     */
    protected function defaultHandler()
    {
        return \flexibuild\file\handlers\DefaultHandler::className();
    }
}
