<?php

namespace flexibuild\file\formatters\pdf;

use flexibuild\file\formatters\Formatter;
use flexibuild\file\helpers\FileSystemHelper;
use flexibuild\file\helpers\PdfHelper;

/**
 * Formatter that generates preview image for pdf file.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class PreviewFormatter extends Formatter
{
    /**
     * @var string format of image, that must be generated.
     * Param may be one of the supported by [[Imagick::setImageFormat()]].
     * @link http://php.net/manual/en/imagick.setimageformat.php
     */
    public $type = 'jpg';

    /**
     * @inheritdoc
     */
    public function format($readFilePath)
    {
        $tmpFilePath = FileSystemHelper::getNewTempFilename($this->type);
        PdfHelper::savePreview($readFilePath, $tmpFilePath);
        return $tmpFilePath;
    }
}
