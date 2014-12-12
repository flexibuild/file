<?php

namespace flexibuild\file\widgets;

use yii\helpers\Json;

/**
 * The ChangeFormEnctype trait adds methods that allow change form enc-type 
 *  to 'multipart/form-data' automatically with using javascript.
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 * 
 * @property \yii\web\View $view The view object that can be used to render views or view files.
 * @method \yii\web\View getView() returns the view object that can be used to render views or view files.
 */
trait FormEnctypeTrait
{
    /**
     * @var boolean whether the widget must to change form enctype automatically or not.
     */
    public $autoSetEnctype = true;

    /**
     * Register scripts that changes form enctype to multipart/form-data.
     * @param string $inputId the id attribute of file input tag.
     */
    public function registerChangeEnctypeScripts($inputId)
    {
        if (!$this->autoSetEnctype) {
            return;
        }

        // !!! without jquery
        $js = 'jQuery("#" + ' . Json::encode($inputId) . ').closest("form").attr("enctype", "multipart/form-data");';
        $this->getView()->registerJs($js);
    }
}
