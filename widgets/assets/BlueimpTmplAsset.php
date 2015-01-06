<?php

namespace flexibuild\file\widgets\assets;

/**
 * BlueimpTmplAsset is used for connecting css & js scripts of blueimp tmpl library.
 * @link https://github.com/blueimp/JavaScript-Templates
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */

class BlueimpTmplAsset extends AssetBundle
{
    public $sourcePath = '@bower/blueimp-tmpl';
    public $allowedDirectories = [
        'js',
    ];
    public $js = [
        'js/tmpl.js',
    ];
}
