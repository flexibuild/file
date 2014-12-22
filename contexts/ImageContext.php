<?php

namespace flexibuild\file\contexts;

use yii\validators\ImageValidator;

/**
 * Context for images. Main logic - use image validator as default.
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class ImageContext extends Context
{
    /**
     * @inheritdoc
     */
    public $inputAccept = 'image/*';

    /**
     * @inheritdoc
     */
    protected function defaultValidators()
    {
        return [ImageValidator::className()];
    }
}
