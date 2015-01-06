<?php

namespace flexibuild\file\widgets\assets;

/**
 * BlueimpFileUploadAsset is used for connecting css & js scripts of
 * blueimp jQuery file upload library.
 * @link https://github.com/blueimp/jQuery-File-Upload
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class BlueimpFileUploadAsset extends AssetBundle
{
    public $sourcePath = '@bower/blueimp-file-upload';
    public $allowedDirectories = [
        'css',
        'js',
        'img',
    ];

    public $js = [
        'js/jquery.iframe-transport.js',
        'js/jquery.fileupload.js',
    ];
    public $css = [
        'css/jquery.fileupload.css',
    ];
    public $depends = [
        'yii\jui\JuiAsset',
    ];
}
