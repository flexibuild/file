<?php

namespace flexibuild\file\widgets\assets;

use yii\web\AssetBundle;

/**
 * FlexibuildFileAsset class for the flexibuild/file extension assets.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class FlexibuildFileAsset extends AssetBundle
{
    public $sourcePath = '@flexibuild/file/assets/flexibuild-file';
    public $js = [
        'flexibuild-file.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\web\JqueryAsset',
    ];
}
