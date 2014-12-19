<?php

namespace flexibuild\file\widgets;

use yii\widgets\ActiveForm;
use yii\base\Model;

/**
 * ActiveFormEx class extends [[\yii\widgets\ActiveForm]] widget for using [[ActiveFieldEx]] class.
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
    public $fieldClass = 'flexibuild\file\widgets\ActiveFieldEx';
}
