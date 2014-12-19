<?php

namespace flexibuild\file\widgets\bootstrap;

use yii\bootstrap\ActiveForm;
use yii\base\Model;

/**
 * ActiveFormEx class extends [[\yii\bootstrap\ActiveForm]] widget for using [[ActiveFieldEx]] class.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 * 
 * @method ActiveFieldEx field(Model $model, string $attribute, array $options = []) generates a form ActiveFieldEx field object.
 */
class ActiveFormEx extends ActiveForm
{
    /**
     * @inheritdoc
     */
    public $fieldClass = 'flexibuild\file\widgets\bootstrap\ActiveFieldEx';
}
