<?php

namespace flexibuild\file\widgets;

use yii\widgets\InputWidget;
use yii\helpers\Html;

/**
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class SimpleFile extends InputWidget
{
    /**
     * @inheritdoc
     */
    public function run()
    {
        $result = Html::activeHiddenInput($this->model, $this->attribute, [
            'id' => null,
            'value' => Html::getInputName($this->model, $this->attribute),
        ]);
        $result .= Html::activeInput('file', $this->model, $this->attribute, $this->options);
        echo $result;
    }
}
