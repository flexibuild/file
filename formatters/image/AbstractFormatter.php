<?php

namespace flexibuild\file\formatters\image;

use yii\base\Exception;

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
        $this->initImagine();
        try {
            $result = $this->processFile($readFilePath);
        } catch (\Exception $ex) {
            $this->restoreImagine();
            throw $ex;
        }
        $this->restoreImagine();

        if ($result instanceof ManipulatorInterface) {
            $decodedReadFilePath = FileSystemHelper::decodeFilename($readFilePath);

            if ($decodedReadFilePath === false) {
                $extension = null;
            } else {
                $extension = FileSystemHelper::extension($decodedReadFilePath);
                $extension = FileSystemHelper::encodeFilename($extension);
                $extension = $extension === false ? null : $extension;
            }
            $tempFile = FileSystemHelper::getNewTempFilename($extension);

            try {
                $result->save($tempFile);
            } catch (\Exception $ex) {
                throw new Exception("Cannot save temp file: $tempFile. " . $ex->getMessage(), $ex->getCode(), $ex);
            }
            $result = $tempFile;
        }
        return $result;
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
