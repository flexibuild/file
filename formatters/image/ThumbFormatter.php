<?php

namespace flexibuild\file\formatters\image;

use flexibuild\file\helpers\ImageHelper;

/**
 * Formatter that resizes image file.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class ThumbFormatter extends AbstractFormatter
{
    /**
     * @var integer|null integer width value which the result picture must have.
     * Null meaning no limit on width.
     */
    public $width;

    /**
     * @var integer|null integer height value which the result picture must have.
     * Null meaning no limit on height.
     */
    public $height;

    /**
     * This value is used only when both `$height` and `$width` params are set.
     * @var mixed how this formatter must crop image if `$height`/`$width` proportion is not equals image proportion.
     * Can be one of the followings:
     * 
     * - false, formatter will not crop image. Note the picture may be very streched or compressed in a result.
     * - 'center' (default), formatter will crop max area in the center of image.
     * - 'top-left', formatter will crop max image area beginning from top-left point.
     * - 'top-right', formatter will crop max image area beginning from top-right point.
     * - 'bottom-left', formatter will crop max image area beginning from bottom-left point.
     * - 'bottom-right', formatter will crop max image area beginning from bottom-right point.
     * - 'inset', the method will works as [[\yii\imagine\Image::thumbnail()]] with [[ManipulateInterface::THUMBNAIL_INSET]] mode, that means
     * both sides will be scaled down until they match or are smaller than the parameter given for the side.
     * - 'outbound', the method will works as [[\yii\imagine\Image::thumbnail()]] with [[ManipulateInterface::THUMBNAIL_OUTBOUND]] mode, that means
     * creating a fixed size thumbnail by first scaling the image up or down and cropping a specified area from the center.
     */
    public $mode = ImageHelper::THUMBNAIL_CROP_CENTER;

    /**
     * @inheritdoc
     */
    protected function processFile($readFilePath)
    {
        return ImageHelper::thumbnail($readFilePath, $this->width, $this->height, $this->mode);
    }
}
