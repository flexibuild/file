<?php

namespace flexibuild\file\formatters\image;

use Yii;
use yii\base\Exception;
use yii\base\ErrorHandler;

use flexibuild\file\helpers\ImageHelper;
use flexibuild\file\helpers\FileSystemHelper;
use flexibuild\file\formatters\Formatter;

use Imagine\Image\ManipulatorInterface;

/**
 * Abstract formatter class that will be used as base class for others image classes.
 * 
 * This class add `$imagine` param. This param will be passed to [[ImageHelper::setImagine()]].
 * After formatting old value of [[ImageHelper::getImagine()]] will be returned in [[ImageHelper]].
 * This allow you to use [[ImageHelper]] class in subclasses and will sure that [[ImageHelper::getImagine()]] is configured according to [[self::$imagineConfig]] param.
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
abstract class AbstractFormatter extends Formatter
{
    /**
     * @var mixed imagine driver that will be used while this formatter works.
     * You can overload it by one of this:
     * 
     * - 'gd2'|'imagick'|'gmagick', driver name;
     * - ImagineInterface, an imagine instance;
     * - mixed, configuration for creating imagine instance by [[Yii::createObject()]];
     * - null (by default), [[Image::getImagine()]] will be used.
     */
    public $imagineConfig;

    /**
     * @var string|null format of image, that must be generated.
     * Null (default) meaning extension from `$readFilePath` will be used.
     */
    public $extension = null;

    /**
     * @var array options for [[Imagine\Image\ManipulatorInterface::save()]] method.
     * One of the important option is 'quality':
     * integer between 0 (the best compression, the worst quality) and
     * 100 (no compression, the best quality).
     */
    public $saveOptions = [];

    /**
     * @var string|null the path (or alias) of a PHP file containing MIME type information.
     */
    public $mimeMagicFile = '@flexibuild/file/formatters/image/mimeTypes.php';

    /**
     * @var boolean whether need to auto rotate image before processing. True is default.
     */
    public $autoRotate = true;

    /**
     * Keeps old [[ImageHelper::getImagine()]] value.
     * @var \Imagine\Image\ImagineInterface
     */
    private $_oldImagine;

    /**
     * Converts image file to the format that current formatter implements.
     * This method must be overrided in subclasses and consists of formatter logic.
     * @param string $readFilePath path to file that must be read.
     * @return ManipulatorInterface|string ManipulatorInterface object of formatted image or string path to temp file (formatted file version).
     * Note this file will be deleted by other objects that use this method.
     * @throws \Exception if file cannot be formatted.
     */
    abstract protected function processFile($readFilePath);

    /**
     * @inheritdoc
     */
    public function format($readFilePath)
    {
        $extension = $this->parseFileExtension($readFilePath);
        $processFilePath = $readFilePath;

        if ($this->autoRotate) {
            Yii::beginProfile($profileToken = "Auto rotating image: $readFilePath.", __METHOD__);
            if ($tempFile = ($this->rotateImage($readFilePath, $extension) ?: null)) {
                $processFilePath = $tempFile;
            }
            Yii::endProfile($profileToken, __METHOD__);
        }

        $this->initImagine();
        try {
            Yii::beginProfile($profileToken = static::className() . "::processFile($readFilePath)", __METHOD__);
            $result = $this->processFile($processFilePath);
        } catch (\Exception $ex) {
            Yii::endProfile($profileToken, __METHOD__);
            if (isset($tempFile) && !@unlink($tempFile)) {
                Yii::warning("Cannot unlink temp file: $tempFile.", __METHOD__);
            }
            $this->restoreImagine();
            throw $ex;
        }
        Yii::endProfile($profileToken, __METHOD__);
        if (isset($tempFile) && !@unlink($tempFile)) {
            Yii::warning("Cannot unlink temp file: $tempFile.", __METHOD__);
        }
        $this->restoreImagine();

        if ($result instanceof ManipulatorInterface) {
            $tempFile = FileSystemHelper::getNewTempFilename($extension);

            try {
                $result->save($tempFile, $this->saveOptions);
            } catch (\Exception $ex) {
                throw new Exception("Cannot save temp file: $tempFile. " . $ex->getMessage(), $ex->getCode(), $ex);
            }
            $result = $tempFile;
        }
        return $result;
    }

    /**
     * Rotates image if need.
     * @param string $readFilePath path to file that must be checked and rotated (if need).
     * @param string|null|false $extension extension for new temp file.
     * Default is false, meaning extension must be parsed from `$readFilePath` file.
     * @return string|null new temp file path, or null if rotating is not necessary.
     */
    protected function rotateImage($readFilePath, $extension = false)
    {
        try {
            $result = ImageHelper::normalizeRotating($readFilePath);
            if (!$result) {
                return null;
            }

            if ($extension === false) {
                $extension = $this->parseFileExtension($readFilePath);
            }
            $tempFile = FileSystemHelper::getNewTempFilename($extension);
            $result->save($tempFile, $this->saveOptions);
            return $tempFile;
        } catch (\Exception $ex) {
            Yii::error('Cannot normalize rotating. ' . ErrorHandler::convertExceptionToString($ex), __METHOD__);
            return null;
        }
    }

    /**
     * Parses extension from file path.
     * It returns [[self::$extension]] is the last is set.
     * Otherwise the method tries to detect file mime type.
     * If mime type was not detected the method tries to parse file extension.
     * @param string $readFilePath path to file which extension must be parsed.
     * @return string|null parsed extension in local file system charset or null if extension is unknown.
     */
    protected function parseFileExtension($readFilePath)
    {
        if ($this->extension !== null) {
            return $this->extension;
        }

        // try to determine mime type
        $mimeType = FileSystemHelper::getMimeType($readFilePath, null, true);
        if ($mimeType !== null) {
            $extensions = FileSystemHelper::getExtensionsByMimeType($mimeType, $this->mimeMagicFile);
            if (is_array($extensions) && count($extensions)) {
                return reset($extensions);
            }
        }

        // try to parse extension
        $decodedReadFilePath = FileSystemHelper::decodeFilename($readFilePath);
        if ($decodedReadFilePath === false) {
            return null;
        }
        $extension = FileSystemHelper::extension($decodedReadFilePath);
        if ($extension === null || $extension === '') {
            return $extension;
        }
        $fsExtension = FileSystemHelper::encodeFilename($extension);
        return $fsExtension === false ? null : $fsExtension;
    }

    /**
     * Initializes [[ImageHelper::getImagine()]] value.
     * Remembers old imagine value.
     */
    protected function initImagine()
    {
        $this->_oldImagine = ImageHelper::getImagineConfig();
        ImageHelper::setImagine($this->imagineConfig);
    }

    /**
     * Restores [[ImageHelper::getImage()]]] value.
     */
    protected function restoreImagine()
    {
        ImageHelper::setImagine($this->_oldImagine);
    }
}
