<?php

namespace flexibuild\file\events;

/**
 * This event class used for events triggerred by File object when it unsuccessfully trying to get url.
 * @see [[File::getUrl()]] method.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class CannotGetUrlEvent extends FileEvent
{
    /**
     * Constant that keeps case value which means the file is empty (`$file->status === 'empty'`).
     */
    const CASE_EMPTY_FILE = 'empty-file';
    /**
     * Constant that keeps case value which means the file has been just uploaded through web request and has not any http url.
     */
    const CASE_FILE_JUST_UPLOADED = 'file-just-uploaded';
    /**
     * Constant that keeps case value which means the file was not found in storage.
     */
    const CASE_FILE_NOT_FOUND = 'file-not-found';
    /**
     * Constant that keeps case value which means the formatted version of the file was not found in storage.
     */
    const CASE_FORMAT_NOT_FOUND = 'format-not-found';
    /**
     * Constant that keeps case value which means an exception has been thrown while trying to get url from storage.
     */
    const CASE_EXCEPTION_THROWED = 'exception-throwed';

    /**
     * @var string format that was try to get.
     */
    public $format;

    /**
     *
     * @var boolean|string $scheme the URI scheme that used in called getUrl() method:
     *
     * - `false` (default): generating a relative URL.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::hostInfo]].
     * - string: generating an absolute URL with the specified scheme (either `http` or `https`).
     */
    public $scheme;

    /**
     * This option may be used for setting result url in the event handlers.
     * @var string|null string result url value or null.
     * Null meaning exception will be thrown (by default).
     */
    public $url;

    /**
     * @var string value that describes the concrete case why file was not found.
     * May be one of:
     * 
     * - [[self::CASE_EMPTY_FILE]], which means the file is empty (`$file->status === 'empty'`).
     * - [[self::CASE_FILE_JUST_UPLOADED]], which means the file has been just uploaded through web request and has not any http url.
     * - [[self::CASE_FILE_NOT_FOUND]], which means the file was not found in storage.
     * - [[self::CASE_FORMAT_NOT_FOUND]], which means the formatted version of the file was not found in storage.
     * - [[self::CASE_EXCEPTION_THROWED]], which means an exception has been thrown while trying to get url from storage.
     */
    public $case;

    /**
     * Exception that has been thrown.
     * This property is used only for `$case` === [[self::CASE_EXCEPTION_THROWED]].
     * @var \Exception
     */
    public $exception;
}
