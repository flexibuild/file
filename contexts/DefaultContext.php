<?php

namespace flexibuild\file\contexts;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;

use flexibuild\file\storages\filesys\SimpleStorage;

/**
 * Contexts used in file manager for separating different file types (contexts).
 * 
 * For example, you may be want use ImageContext with local file system storage
 * for user avatars and PdfContext with some CDN storage. It is possible. 
 * You can configure your file manager and use the appropriate contexts with
 * the corresponding fields.
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class DefaultContext extends Component
{
    /**
     * @var \flexibuild\file\storages\AbstractStorage
     * Storage for keeping files. By default used file system simple storage.
     * @see \flexibuild\file\storages\filesys\SimpleStorage
     */
    public $storage;

    /**
     * @inheritdoc
     * @throws InvalidConfigException if storage property has invalid value.
     */
    public function init()
    {
        parent::init();

        if ($this->storage === null) {
            $this->storage = SimpleStorage::className();
        }
        if (!is_object($this->storage)) {
            $this->storage = Yii::createObject($this->storage);
        }
    }
}
