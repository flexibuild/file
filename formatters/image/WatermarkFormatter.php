<?php

namespace flexibuild\file\formatters\image;

use flexibuild\file\helpers\ImageHelper;

/**
 * This formatter allows you to add watermark on image.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class WatermarkFormatter extends AbstractFormatter
{
    /**
     * @var string required string path to watermark file.
     */
    public $filename;

    /**
     * @var mixed coordinate `x` of place where will be added watermark. Can be one of the followings:
     * 
     * - integer, concrete coordinate that will be used.
     * - 'random' or [[ImageHelper::WATERMARK_RANDOM]], mode means watermark will be added in random place.
     * - 'repeat' or [[ImageHelper::WATERMARK_REPEAT]], mode means watermark will be repeated.
     * - 'center' or [[ImageHelper::WATERMARK_CENTER]] (default), mode means watermark will be placed in center.
     * - 'left' or [[ImageHelper::WATERMARK_LEFT]], mode means watermark will be placed in left place.
     * - 'right' or [[ImageHelper::WATERMARK_RIGHT]], mode means watermark will be placed in right place.
     * 
     */
    public $x = ImageHelper::WATERMARK_CENTER;

    /**
     * @var mixed coordinate `x` of place where will be added watermark. Can be one of the followings:
     * 
     * - integer, concrete coordinate that will be used.
     * - 'random' or [[ImageHelper::WATERMARK_RANDOM]], mode means watermark will be added in random place.
     * - 'repeat' or [[ImageHelper::WATERMARK_REPEAT]], mode means watermark will be repeated.
     * - 'middle' or [[ImageHelper::WATERMARK_MIDDLE]] (default), mode means watermark will be placed in middle place.
     * - 'top' or [[ImageHelper::WATERMARK_TOP]], mode means watermark will be placed in top place.
     * - 'bottom' or [[ImageHelper::WATERMARK_BOTTOM]], mode means watermark will be placed in bottom place.
     * 
     */
    public $y = ImageHelper::WATERMARK_MIDDLE;

    /**
     * @inheritdoc
     */
    public function processFile($readFilePath)
    {
        return ImageHelper::watermark($readFilePath, $this->filename, [$this->x, $this->y]);
    }
}
