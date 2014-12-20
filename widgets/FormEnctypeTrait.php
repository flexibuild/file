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
     * @var boolean whether the widget may to use jQuery library. True by default.
     */
    public $useJQuery = true;

    /**
     * Register scripts that changes form enctype to multipart/form-data.
     * @param string $inputId the id attribute of file input tag.
     */
    public function registerChangeEnctypeScripts($inputId)
    {
        if (!$this->autoSetEnctype) {
            return;
        }
        $encodedInputId = Json::encode($inputId);

        if ($this->useJQuery) {
            $js = "jQuery('#' + $encodedInputId).closest('form').attr('enctype', 'multipart/form-data');";
            $this->getView()->registerJs($js);
            return;
        }

        $js = <<<JS
            (function () {
                var onReady = function () {
                        var input = document.getElementById($encodedInputId), parentNode = input;
                        while ((parentNode = parentNode.parentNode) && (parentNode.tagName.toUpperCase() !== 'FORM'));
                        parentNode && parentNode.setAttribute && parentNode.setAttribute("enctype", "multipart/form-data");
                    },
                    completed = function () {
                        document.removeEventListener && document.removeEventListener("DOMContentLoaded", completed, false);
                        window.removeEventListener && window.removeEventListener("load", completed, false);
                        onReady();
                    };
                if (document.readyState === "complete") {
                    setTimeout(onReady);
                } else {
                    document.addEventListener && document.addEventListener("DOMContentLoaded", completed, false);
                    window.addEventListener && window.addEventListener("load", completed, false);
                }
            })();
JS;
        $this->getView()->registerJs($js, \yii\web\View::POS_END);
    }
}
