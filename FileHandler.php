<?php

namespace flexibuild\file;

use yii\base\Component;
use yii\base\InvalidConfigException;

use flexibuild\file\events\CannotGetUrlEvent;
use flexibuild\file\events\CannotGetUrlHandlers;
use flexibuild\file\events\DataChangedEvent;
use flexibuild\file\events\FileEvent;
use flexibuild\file\events\FileValidEvent;
use flexibuild\file\events\BeforeDeleteEvent;
use flexibuild\file\events\AfterDeleteEvent;

/**
 * Class that implements events logic for [[File]] class.
 * 
 * Objects of this class are associated as one-to-one with [[File]] objects.
 * It is because [[File]] class extends [[\yii\web\UploadedFile]] that extends [[\yii\base\Object]] and does not implement events logic.
 * On other hand we have to use [[File]] that extended from [[\yii\web\UploadedFile]] class for capability with standart yii file validators.
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class FileHandler extends Component
{
    /**
     * @event CannotGetUrlEvent an event raised when cannot get url.
     */
    const EVENT_CANNOT_GET_URL = 'cannotGetUrl';

    /**
     * @event DataChangedEvent an event raised when data was changed.
     */
    const EVENT_DATA_CHANGED = 'dataChanged';

    /**
     * @event FileValidEvent an event raised before file saving.
     * You can set [[FileValidEvent::$valid]] property as false to stop saving process.
     */
    const EVENT_BEFORE_SAVE = 'beforeSave';

    /**
     * @event FileEvent an event raised after file saving.
     */
    const EVENT_AFTER_SAVE = 'afterSave';

    /**
     * @event BeforeDeleteEvent an event raised before file deleting.
     * You can set [[BeforeDeleteEvent::$valid]] property as false to stop deleting process.
     */
    const EVENT_BEFORE_DELETE = 'beforeDelete';

    /**
     * @event AfterDeleteEvent an event raised after file deleting.
     */
    const EVENT_AFTER_DELETE = 'afterDelete';

    /**
     * [[File]] object linked as one-to-one with with this object.
     * @var File
     */
    public $file;

    /**
     * @inheritdoc
     * @throws InvalidConfigException if a required property is not define.
     */
    public function init()
    {
        parent::init();

        if (!$this->file instanceof File) {
            throw new InvalidConfigException('An instance of '.File::className().' is required for '.get_class($this).'.');
        }
    }

    /**
     * !!!
     * @param type $oldData
     * @param type $newData
     */
    public function triggerDataChangedEvent($oldData, $newData)
    {
        $event = new DataChangedEvent();
        $event->sender = $this;
        $event->file = $this->file;
        $event->oldFileData = $oldData;
        $event->newFileData = $newData;

        $this->trigger(self::EVENT_DATA_CHANGED, $event);
    }

    /**
     * !!!
     * @param type $case
     * @param type $format
     * @param type $scheme
     * @param type $exception
     * @return string|null
     */
    public function triggerCannotGetUrl($case, $format = null, $scheme = null, $exception = null)
    {
        $event = new CannotGetUrlEvent();
        $event->sender = $this;
        $event->file = $this->file;
        $event->case = $case;
        $event->format = $format;
        $event->scheme = $scheme;
        $event->exception = $exception;

        $this->trigger(self::EVENT_CANNOT_GET_URL, $event);

        if ($event->url !== null) {
            return $event->url;
        }
        CannotGetUrlHandlers::throwException($event);
    }

    /**
     * Triggers beforeSave event.
     * @return boolean whether continue saving process or stop it.
     */
    public function triggerBeforeSave()
    {
        $event = new FileValidEvent();
        $event->sender = $this;
        $event->file = $this->file;
        $event->valid = true;

        $this->trigger(self::EVENT_BEFORE_SAVE, $event);
        return (bool) $event->valid;
    }

    /**
     * Triggers afterSave event.
     */
    public function triggerAfterSave()
    {
        $event = new FileEvent();
        $event->sender = $this;
        $event->file = $this->file;

        $this->trigger(self::EVENT_AFTER_SAVE, $event);
    }

    /**
     * Triggers beforeDelete event.
     * @param string $format  string name of format which will be deleted.
     * Null meaning source file was deleted.
     * @return boolean whether continue deleting process or stop it.
     */
    public function triggerBeforeDelete($format = null)
    {
        $event = new BeforeDeleteEvent();
        $event->sender = $this;
        $event->file = $this->file;
        $event->valid = true;
        $event->format = $format;

        $this->trigger(self::EVENT_BEFORE_DELETE, $event);
        return (bool) $event->valid;
    }

    /**
     * Triggers afterDelete event.
     * @param string $format  string name of format which will be deleted.
     * Null meaning source file was deleted.
     */
    public function triggerAfterDelete($format = null)
    {
        $event = new AfterDeleteEvent();
        $event->sender = $this;
        $event->file = $this->file;
        $event->format = $format;

        $this->trigger(self::EVENT_AFTER_DELETE, $event);
    }
}
