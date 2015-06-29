<?php

namespace flexibuild\file\helpers;

use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\base\InvalidValueException;

use yii\imagine\Image;
use yii\imagine\BaseImage;
use Imagine\Image\Box;
use Imagine\Image\Color;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\ManipulatorInterface;
use Imagine\Image\Point;

/**
 * BaseImageHelper class that extends [[BaseImage]] functionality.
 * Do not use this class, lets use [[ImageHelper]].
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class BaseImageHelper extends BaseImage
{
    /**
     * Modes for [[self::thumbnail()]] method.
     * 
     * Mode means image there will no be cropped. Note the picture may be very streched or compressed in a result.
     */
    const THUMBNAIL_CROP_NONE = false;
    /**
     * Default mode means there will be cropped max area in the center of image.
     */
    const THUMBNAIL_CROP_CENTER = 'center';
    /**
     * Mode means there will be cropped max image area beginning from top-left point.
     */
    const THUMBNAIL_CROP_TOP_LEFT = 'top-left';
    /**
     * Mode means there will be cropped max image area beginning from top-right point.
     */
    const THUMBNAIL_CROP_TOP_RIGHT = 'top-right';
    /**
     * Mode means there will be cropped max image area beginning from bottom-left point.
     */
    const THUMBNAIL_CROP_BOTTOM_LEFT = 'bottom-left';
    /**
     * Mode means there will be cropped max image area beginning from bottom-right point.
     */
    const THUMBNAIL_CROP_BOTTOM_RIGHT = 'bottom-right';
    /**
     * Mode means the thumbnail method will work as parent method, it will use [[ManipulateInterface::THUMBNAIL_INSET]] mode, that means
     * both sides will be scaled down until they match or are smaller than the parameter given for the side.
     */
    const THUMBNAIL_INSET = 'inset';
    /**
     * Mode means the thumbnail method will work as 'inset' method, but it will always resize image.
     */
    const THUMBNAIL_INSET_FORCE = 'inset-force';
    /**
     * Mode means the thumbnail method will work as parent method, it will use [[ManipulateInterface::THUMBNAIL_OUTBOUND]] mode, that means
     * creating a fixed size thumbnail by first scaling the image up or down and cropping a specified area from the center.
     */
    const THUMBNAIL_OUTBOUND = 'outbound';
    /**
     * Mode means the thumbnail method will work as 'outbound' method, but it will always resize image.
     */
    const THUMBNAIL_OUTBOUND_FORCE = 'outbound-force';

    /**
     * Constants that may be used in [[self::watermark()]] method.
     * 
     * Mode means watermark will be added in random place.
     */
    const WATERMARK_RANDOM = 'random';
    /**
     * Mode means watermark will be repeated.
     */
    const WATERMARK_REPEAT = 'repeat';
    /**
     * Default mode which means watermark will be placed in center.
     */
    const WATERMARK_CENTER = 'center';
    /**
     * Mode means watermark will be placed in left place.
     */
    const WATERMARK_LEFT = 'left';
    /**
     * Mode means watermark will be placed in right place.
     */
    const WATERMARK_RIGHT = 'right';
    /**
     * Mode means watermark will be placed in middle place.
     */
    const WATERMARK_MIDDLE = 'middle';
    /**
     * Mode means watermark will be placed in top place.
     */
    const WATERMARK_TOP = 'top';
    /**
     * Mode means watermark will be placed in bottom place.
     */
    const WATERMARK_BOTTOM = 'bottom';

    /**
     * Keeps imagine instance or configuration.
     * @var mixed
     */
    private static $_imagine;

    /**
     * Instantiates and returns imagine driver object.
     * @return ImagineInterface imagine driver.
     * @throw InvalidConfigException if imagine has incorrect format.
     */
    public static function getImagine()
    {
        if (self::$_imagine === null) {
            return Image::getImagine();
        } elseif (self::$_imagine instanceof ImagineInterface) {
            return self::$_imagine;
        } elseif (self::$_imagine === self::DRIVER_GD2) {
            return self::$_imagine = new \Imagine\Gd\Imagine();
        } elseif (self::$_imagine === self::DRIVER_GMAGICK) {
            return self::$_imagine = new \Imagine\Gmagick\Imagine();
        } elseif (self::$_imagine === self::DRIVER_IMAGICK) {
            return self::$_imagine = new \Imagine\Imagick\Imagine();
        }

        self::$_imagine = Yii::createObject(self::$_imagine);
        if (!self::$_imagine instanceof ImagineInterface) {
            throw new InvalidConfigException('Param $imagine must be an instance of Imagine\Image\ImagineInterface.');
        }
        return self::$_imagine;
    }

    /**
     * @param ImagineInterface $imagine imagine driver that will be used.
     * You can use one of this:
     * 
     * -  'gd2'|'imagick'|'gmagick', driver name;
     * - ImagineInterface, an imagine instance;
     * - mixed, configuration for creating imagine instance by [[Yii::createObject()]];
     * - null (by default), [[Image::getImagine()]] will be used.
     */
    public static function setImagine($imagine)
    {
        self::$_imagine = $imagine;
    }

    /**
     * @internal internally used in [[\flexibuild\file\formatters\image\AbstractFormatter]] for getting configuration.
     * Returns imagine configuration.
     * @return mixed
     */
    public static function getImagineConfig()
    {
        return self::$_imagine;
    }

    /**
     * Creates a thumbnail image. It keeps the aspect ratio of the image.
     * This method changes parent method by setting other defaults for params and more mode values.
     * 
     * @param string $filename the image file path or path alias.
     * @param integer $width the width in pixels to create the thumbnail.
     * Null meaning current width will be used (default).
     * @param integer $height the height in pixels to create the thumbnail.
     * Null meaning current width will be used (default).
     * @param string $mode resize mode, may be one of the followings:
     * 
     * - false or [[self::THUMBNAIL_CROP_NONE]], the method will not crop image. Note the picture may be very streched or compressed in a result.
     * - 'center' or [[self::THUMBNAIL_CROP_CENTER]] (default), the method will crop max area in the center of image.
     * - 'top-left' or [[self::THUMBNAIL_CROP_TOP_LEFT]], the method will crop max image area beginning from top-left point.
     * - 'top-right' or [[self::THUMBNAIL_CROP_TOP_RIGHT]], the method will crop max image area beginning from top-right point.
     * - 'bottom-left' or [[self::THUMBNAIL_CROP_BOTTOM_LEFT]], the method will crop max image area beginning from bottom-left point.
     * - 'bottom-right' or [[self::THUMBNAIL_CROP_BOTTOM_RIGHT]], the method will crop max image area beginning from bottom-right point.
     * - 'inset' or [[self::THUMBNAIL_INSET]], the method will work as parent method, it will use [[ManipulateInterface::THUMBNAIL_INSET]] mode, that means
     * both sides will be scaled down until they match or are smaller than the parameter given for the side.
     * - 'inset-force' or [[self::THUMBNAIL_INSET_FORCE]], the method means the thumbnail method will work as 'inset' method,
     * but it will always resize image
     * - 'outbound' or [[self::THUMBNAIL_OUTBOUND]], the method will work as parent method, it will use [[ManipulateInterface::THUMBNAIL_OUTBOUND]] mode, that means
     * creating a fixed size thumbnail by first scaling the image up or down and cropping a specified area from the center.
     * - 'outbound-force' or [[self::THUMBNAIL_OUTBOUND_FORCE]], the method means the thumbnail method will work as 'outbound' method,
     * but it will always resize image.
     * 
     * @param string $backgroundColor string color in RGB style. Default is 'FFF';
     * This color will be used as background if image height or width less than needed.
     * Used only for inset and outbound modes.
     * 
     * @return ImageInterface
     * @throws InvalidParamException if `$mode` value is incorrect.
     */
    public static function thumbnail($filename, $width = null, $height = null, $mode = self::THUMBNAIL_CROP_CENTER, $backgroundColor = 'FFF')
    {
        if ($width === null && $height === null) {
            return static::getImagine()->open(Yii::getAlias($filename))->copy();
        }

        $img = static::getImagine()->open(Yii::getAlias($filename));

        if ($width === null) {
            $imgSize = $img->getSize();
            $box = $imgSize->heighten($height);
            $width = $box->getWidth();
        } elseif ($height === null) {
            $imgSize = $img->getSize();
            $box = $imgSize->widen($width);
            $height = $box->getHeight();
        } else {
            $box = new Box($width, $height);
        }

        $parentModes = [
            self::THUMBNAIL_INSET,
            self::THUMBNAIL_INSET_FORCE,
            self::THUMBNAIL_OUTBOUND,
            self::THUMBNAIL_OUTBOUND_FORCE,
        ];
        if (in_array($mode, $parentModes, true)) {
            // parent (yii/imagine) logic
            $imgSize = isset($imgSize) ? $imgSize : $img->getSize();

            if ($mode === self::THUMBNAIL_INSET || $mode === self::THUMBNAIL_OUTBOUND) {
                if ($imgSize->getWidth() <= $box->getWidth() && $imgSize->getHeight() <= $box->getHeight()) {
                    return $img->copy();
                }
            }
            if (!$box->getWidth() && !$box->getHeight()) {
                return $img->copy();
            }

            $mode = in_array($mode, [self::THUMBNAIL_INSET, self::THUMBNAIL_INSET_FORCE], true)
                ? ManipulatorInterface::THUMBNAIL_INSET
                : ManipulatorInterface::THUMBNAIL_OUTBOUND;
            $img = $img->thumbnail($box, $mode);

            // create empty image to preserve aspect ratio of thumbnail
            $thumb = static::getImagine()->create($box, new Color($backgroundColor, 100));

            // calculate points
            $size = $img->getSize();

            $startX = 0;
            $startY = 0;
            if ($size->getWidth() < $width) {
                $startX = ceil(($width - $size->getWidth()) / 2);
            }
            if ($size->getHeight() < $height) {
                $startY = ceil(($height - $size->getHeight()) / 2);
            }

            $thumb->paste($img, new Point($startX, $startY));

            return $thumb;
        }

        //self (flexibuild/file) logic
        if ($mode === self::THUMBNAIL_CROP_NONE) {
            return $img->copy()->resize($box);
        }

        $imgSize = isset($imgSize) ? $imgSize : $img->getSize();
        $imgWidth = $imgSize->getWidth();
        $imgHeight = $imgSize->getHeight();
        $resizeWidth = $box->getWidth();
        $resizeHeight = $box->getHeight();

        if ($resizeWidth / $imgWidth * $imgHeight > $resizeHeight) {
            $img = $img->copy()->resize($imgSize = $imgSize->widen($resizeWidth));
            $resizedByWidth = true;
        } else {
            $img = $img->copy()->resize($imgSize = $imgSize->heighten($resizeHeight));
            $resizedByWidth = false;
        }

        switch ($mode) {
            case self::THUMBNAIL_CROP_CENTER:
                $start = $resizedByWidth
                    ? new Point(0, floor(($imgSize->getHeight() - $resizeHeight) / 2))
                    : new Point(floor(($imgSize->getWidth() - $resizeWidth) / 2), 0);
                break;

            case self::THUMBNAIL_CROP_TOP_LEFT:
                $start = new Point(0, 0);
                break;

            case self::THUMBNAIL_CROP_TOP_RIGHT:
                $start = new Point($imgSize->getWidth() - $resizeWidth, 0);
                break;

            case self::THUMBNAIL_CROP_BOTTOM_LEFT:
                $start = new Point(0, $imgSize->getHeight() - $resizeHeight);
                break;

            case self::THUMBNAIL_CROP_BOTTOM_RIGHT:
                $start = new Point($imgSize->getWidth() - $resizeWidth, $imgSize->getHeight() - $resizeHeight);
                break;

            default:
                throw new InvalidParamException('Param $mode has incorrect value.');
        }

        return $img->crop($start, $box);
    }

    /**
     * Adds a watermark to an existing image. This method adds more relative varianst for `$start` param.
     * This method also resizes watermark to image size if need.
     * 
     * @param string $filename the image file path or path alias.
     * @param string $watermarkFilename the file path or path alias of the watermark image.
     * @param array $start the starting point. This must be an array with two elements representing `x` and `y` coordinates.
     * 
     * - `x` position: string|integer where watermark must be placed by width. Can be one of the followings:
     *   - integer, concrete coordinate that will be used.
     *   - 'random' or [[self::WATERMARK_RANDOM]], mode means watermark will be added in random place.
     *   - 'repeat' or [[self::WATERMARK_REPEAT]], mode means watermark will be repeated.
     *   - 'center' or [[self::WATERMARK_CENTER]] (default), mode means watermark will be placed in center.
     *   - 'left' or [[self::WATERMARK_LEFT]], mode means watermark will be placed in left place.
     *   - 'right' or [[self::WATERMARK_RIGHT]], mode means watermark will be placed in right place.
     * 
     * - `y` position: string|integer where watermark must be placed by height. Can be one of the followings:
     *   - integer, concrete coordinate that will be used.
     *   - 'random' or [[self::WATERMARK_RANDOM]], mode means watermark will be added in random place.
     *   - 'middle' or [[self::WATERMARK_MIDDLE]] (default), mode means watermark will be placed in middle place.
     *   - 'top' or [[self::WATERMARK_TOP]], mode means watermark will be placed in top place.
     *   - 'bottom' or [[self::WATERMARK_BOTTOM]], mode means watermark will be placed in bottom place.
     * 
     * @return ImageInterface
     * @throws InvalidParamException if `$start` is invalid
     */
    public static function watermark($filename, $watermarkFilename, array $start = [self::WATERMARK_CENTER, self::WATERMARK_MIDDLE])
    {
        if (!isset($start[0], $start[1])) {
            throw new InvalidParamException('$start must be an array of two elements.');
        }

        $img = static::getImagine()->open(Yii::getAlias($filename));
        $watermark = static::getImagine()->open(Yii::getAlias($watermarkFilename));

        $imgSize = $img->getSize();
        $watermarkSize = $watermark->getSize();
        if ($watermarkSize->getWidth() > $imgSize->getWidth() || $watermarkSize->getHeight() > $imgSize->getHeight()) {
            $newWatermarkSize = $watermarkSize->widen($imgSize->getWidth());
            if ($newWatermarkSize->getHeight() > $imgSize->getHeight()) {
                $newWatermarkSize = $watermarkSize->heighten($imgSize->getHeight());
            }
            $watermarkSize = $newWatermarkSize;
            $watermark = $watermark->resize($watermarkSize);
        }

        $array = [
            0 => [$imgSize->getWidth(), $watermarkSize->getWidth()],
            1 => [$imgSize->getHeight(), $watermarkSize->getHeight()],
        ];
        foreach ($array as $index => $lengths) {
            $coordinate = $start[$index];
            list($imgLength, $watermarkLength) = $lengths;

            switch (true) {
                case !is_string($coordinate) || ctype_digit($coordinate):
                    $coordinate = (int) $coordinate;
                    break;

                case $coordinate === self::WATERMARK_CENTER: // no break
                case $coordinate === self::WATERMARK_MIDDLE:
                    $coordinate = $imgLength > $watermarkLength ? floor(($imgLength - $watermarkLength) / 2) : 0;
                    break;

                case $coordinate === self::WATERMARK_LEFT: // no break
                case $coordinate === self::WATERMARK_TOP:
                    $coordinate = 0;
                    break;

                case $coordinate === self::WATERMARK_RIGHT: // no break
                case $coordinate === self::WATERMARK_BOTTOM:
                    $coordinate = $imgLength > $watermarkLength ? $imgLength - $watermarkLength : 0;
                    break;

                case $coordinate === self::WATERMARK_RANDOM:
                    $coordinate = $imgLength > $watermarkLength ? mt_rand(0, $imgLength - $watermarkLength) : 0;
                    break;

                case $coordinate === self::WATERMARK_REPEAT:
                    $coordinate = [$coordinateTmp = 0];
                    if ($watermarkLength <= 0) {
                        throw new InvalidValueException('Watermark length is less than or equal to zero.');
                    }
                    while (($coordinateTmp += $watermarkLength) < $imgLength) {
                        $coordinate[] = $coordinateTmp;
                    }
                    break;

                default:
                    throw new InvalidParamException("Unknown coordinate type: '$coordinate'.");
            }
            $start[$index] = $coordinate;
        }

        foreach ((array) $start[0] as $startX) {
            $watermarkFixedByX = $watermark;
            if ($startX + $watermarkSize->getWidth() > $imgSize->getWidth()) {
                $watermarkFixedByX = $watermark->copy()->crop(
                    new Point(0, 0),
                    new Box(
                        $imgSize->getWidth() - $startX,
                        $watermarkSize->getHeight()
                    )
                );
            }

            foreach ((array) $start[1] as $startY) {
                $fixedWatermark = $watermarkFixedByX;
                if ($startY + $watermarkSize->getHeight() > $imgSize->getHeight()) {
                    $fixedWatermark = $watermarkFixedByX->copy()->crop(
                        new Point(0, 0),
                        new Box(
                            $watermarkFixedByX->getSize()->getWidth(),
                            $imgSize->getHeight() - $startY
                        )
                    );
                }
                $img = $img->paste($fixedWatermark, new Point($startX, $startY));
            }
        }
        return $img;
    }

    /**
     * Grayscales image.
     * @param string $filename the image file path or path alias.
     * @return ImageInterface
     */
    public static function grayscale($filename)
    {
        $img = static::getImagine()->open(Yii::getAlias($filename));
        $img->effects()->grayscale();
        return $img;
    }

    /**
     * Normalized image rotating.
     * @param string $filename path to input file.
     * @return ImageInterface|null Returns normalized image object or null, if rotating is not necessary.
     * @throws Exception if function `exif_read_data()` was not found.
     */
    public static function normalizeRotating($filename)
    {
        if (!function_exists('exif_read_data')) {
            throw new Exception('Function "exif_read_data()" does not found in the PHP core.');
        }
        $path = Yii::getAlias($filename);
        $exif = @exif_read_data($path);

        if (empty($exif) || !is_array($exif) || !isset($exif['Orientation'])) {
            return null;
        }

        /* @link http://sylvana.net/jpegcrop/exif_orientation.html */
        $needMirror = false;
        $rotateDegrees = 0;
        switch($exif['Orientation']){
            case 1:
                return null;
            case 2:
                $needMirror = true;
                break;
            case 3:
                $rotateDegrees = 180;
                break;
            case 4:
                $rotateDegrees = 180;
                $needMirror = true;
                break;
            case 5:
                $rotateDegrees = 90;
                $needMirror = true;
                break;
            case 6:
                $rotateDegrees = 90;
                break;
            case 7:
                $rotateDegrees = -90;
                $needMirror = true;
                break;
            case 8:
                $rotateDegrees = -90;
                break;
            default:
                return null;
        }

        if (!$rotateDegrees && !$needMirror) {
            return null;
        }

        $img = static::getImagine()->open($path);
        if ($rotateDegrees) {
            $img = $img->rotate($rotateDegrees);
        }
        if ($needMirror) {
            $img = $img->flipHorizontally();
        }
        return $img;
    }
}
