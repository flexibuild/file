<?php

namespace flexibuild\file\helpers;

use yii\base\Exception;
use yii\base\InvalidParamException;

/**
 * PdfHelper for working with pdf files.
 * Not use this class, use [[PdfHelper]] instead of.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class BasePdfHelper
{
    /**
     * Generates preview page for pdf file.
     * 
     * @param string $pdfFilePath full path to pdf file.
     * 
     * @param string $imageFormat format of image, that must be generated.
     * Param may be one of the supported by [[Imagick::setImageFormat()]].
     * @link http://php.net/manual/en/imagick.setimageformat.php
     * 
     * @return string generated image content.
     * 
     * @throws Exception if Imagick class not found.
     */
    public static function getPreview($pdfFilePath, $imageFormat = 'jpg')
    {
        if (!class_exists('Imagick', false)) {
            throw new Exception('Imagick class required for ' . __NAMESPACE__ . '\PdfHelper.');
        }

        $imagick = new Imagick(realpath($pdfFilePath));
        $imagick->setimageformat($imageFormat);

        return (string) $imagick;
    }

    /**
     * Generates PDF preview image and saves it.
     * @param string $pdfFilePath full path to pdf file.
     * @param string $destImagePath full path of image file that must be saved.
     * @throws InvalidParamException if cannot write file.
     */
    public static function savePreview($pdfFilePath, $destImagePath)
    {
        $content = static::getPreview($pdfFilePath, FileSystemHelper::extension($destImagePath));
        if (false === @file_put_contents($destImagePath, $content)) {
            throw new InvalidParamException("Cannot save file: $destImagePath");
        }
    }
}
