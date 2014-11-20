<?php

namespace flexibuild\file\events;

use yii\base\Exception;
use yii\db\BaseActiveRecord;

use flexibuild\file\File;
use flexibuild\file\FileHandler;
use flexibuild\file\ModelBehavior;

/**
 * Class that represents some helpful handlers for [[CannotGetUrlEvent]].
 * 
 * !!! examples
 * 
 * @author SeynovAM <senjovalexey@gmail.com>
 */
class CannotGetUrlHandlers
{
    /**
     * Handler for [[CannotGetUrlEvent]], this handler will throw Exception.
     * @param CannotGetUrlEvent $event
     * @throws Exception
     */
    public static function throwException($event)
    {
        $case = $event->case;
        $file = $event->file;
        $format = $event->format;
        $exception = $event->exception;

        switch ($case) {
            case CannotGetUrlEvent::CASE_EMPTY_FILE:
                throw new Exception("Cannot get url for empty file.");
            case CannotGetUrlEvent::CASE_FILE_JUST_UPLOADED:
                throw new Exception("Cannot get url for file '$file->name', the file was just uploaded.");
            case CannotGetUrlEvent::CASE_FILE_NOT_FOUND:
                throw new Exception("Cannot get url for file '$file->name', the file was not found in storage.");
            case CannotGetUrlEvent::CASE_FORMAT_NOT_FOUND:
                throw new Exception("Cannot get url for formatted as '$format' version of file '$file->name', the version was not found in storage.");
            case CannotGetUrlEvent::CASE_EXCEPTION_THROWED:
                throw new Exception("Cannot get url for file '$file->name'.", $exception->getCode(), $exception);
            default:
                throw new Exception('Cannot get url, some unknown server error.');
        }
    }

    /**
     * Handler for [[CannotGetUrlEvent]], this handler will generate formatted version of file on the fly.
     * This handler is used for `$event->case` === [[CannotGetUrlEvent::CASE_FORMAT_NOT_FOUND]] only.
     * 
     * Note! This handler does not save changes of your model file attribute in database. You have the next choices:
     * - do not use this handler
     * - use [[formatFileOnFlyWithSaving]] handler
     * - use storages which does not change file data while saves formatted version of the source.
     * 
     * @param CannotGetUrlEvent $event
     */
    public static function formatFileOnFly($event)
    {
        if ($event->case !== CannotGetUrlEvent::CASE_FORMAT_NOT_FOUND) {
            return;
        }

        try {
            $file = $event->file;
            $file->generateFormat($event->format);
            $url = $file->context->getStorage()->getUrl($file->getData(), $event->format, $event->scheme);
        } catch (\Exception $ex) {
            $event->case = CannotGetUrlEvent::CASE_EXCEPTION_THROWED;
            $event->exception = $ex;
            return;
        }

        if ($url !== null) {
            $event->url = $url;
            $event->handled = true;
        }
    }

    /**
     * Handler for [[CannotGetUrlEvent]], this handler works like [[formatFileOnFly()]].
     * But it unlike [[formatFileOnFly()]] tries to save owner [[ActiveRecord]] after success formatting file.
     * @see [[formatFileOnFly()]]
     * 
     * This handler is used for `$event->case` === [[CannotGetUrlEvent::CASE_FORMAT_NOT_FOUND]] only.
     * 
     * @param CannotGetUrlEvent $event
     */
    public static function formatFileOnFlyWithSaving($event)
    {
        if ($event->case !== CannotGetUrlEvent::CASE_FORMAT_NOT_FOUND) {
            return;
        }

        $file = $event->file;

        // determines whether owner record must be saved or not
        switch (true) {
            case !($dataAttribute = $file->dataAttribute); // no break
            case !($behavior = $file->owner); // no break
            case !$behavior instanceof ModelBehavior; // no break
            case !($record = $behavior->owner); // no break
            case !$record instanceof BaseActiveRecord; // no break
            case $record->getIsNewRecord(); // no break
                // there is saving process is not required
                static::formatFileOnFly($event);
                return;
        }

        $saveHandler = function (DataChangedEvent $changedEvent) use ($record, $dataAttribute) {
            $record->{$dataAttribute} = $changedEvent->newFileData;
            $record->update(false, [$dataAttribute]);
        };

        $fileHandler = $file->handler;
        $fileHandler->on(FileHandler::EVENT_DATA_CHANGED, $saveHandler);
        try {
            static::formatFileOnFly($event);
        } catch (\Exception $ex) {
            $fileHandler->off(FileHandler::EVENT_DATA_CHANGED, $saveHandler);
            throw $ex;
        }
        $fileHandler->off(FileHandler::EVENT_DATA_CHANGED, $saveHandler);
    }

    /**
     * Handler for [[CannotGetUrlEvent]], this handler will return value of default url.
     * @see [[File::getDefaultUrl()]]
     * 
     * @param CannotGetUrlEvent $event
     */
    public static function returnDefaultUrl($event)
    {
        $url = $event->file->getDefaultUrl($event->format, $event->scheme);
        if ($url !== null) {
            $event->url = $url;
            $event->handled = true;
        }
    }

    /**
     * Handler for [[CannotGetUrlEvent]], this handler will return empty string ('').
     * 
     * @param CannotGetUrlEvent $event
     */
    public static function returnEmptyString($event)
    {
        $event->url = '';
        $event->handled = true;
    }

    /**
     * Handler for [[CannotGetUrlEvent]], this handler will return hash ('#').
     * 
     * @param CannotGetUrlEvent $event
     */
    public static function returnHash($event)
    {
        $event->url = '#';
        $event->handled = true;
    }

    /**
     * Handler for [[CannotGetUrlEvent]], this handler will return 'about:blank' string.
     * 
     * @param CannotGetUrlEvent $event
     */
    public static function returnAboutBlank($event)
    {
        $event->url = 'about:blank';
        $event->handled = true;
    }
}
