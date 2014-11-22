<?php

namespace flexibuild\file\formatters\image;

use flexibuild\file\helpers\ImageHelper;

/**
 * Formatter that formatted source image to grayscaled image.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class GrayscaleFormatter extends AbstractFormatter
{
    /**
     * @inheritdoc
     */
    protected function processFile($readFilePath)
    {
        return ImageHelper::grayscale($readFilePath);
    }
}
