<?php

namespace flexibuild\file\widgets\assets;

/**
 * BlueimpWidgetAsset class for [[\flexibuild\file\widgets\BlueimpJQueryUploader]] widget.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>s
 */
class BlueimpWidgetAsset extends \yii\web\AssetBundle
{
    public $sourcePath = '@flexibuild/file/assets/blueimp-jquery-uploader';
    public $js = [
        'flexibuild-blueimp-jquery-uploader.js',
    ];
    public $depends = [
        'flexibuild\file\widgets\assets\BlueimpFileUploadAsset',
        'flexibuild\file\widgets\assets\BlueimpTmplAsset',
    ];
}
