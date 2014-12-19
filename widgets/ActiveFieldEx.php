<?php

namespace flexibuild\file\widgets;

use yii\widgets\ActiveField;

/**
 * ActiveFieldEx class extends [[\yii\widgets\ActiveField]] class with different file typed intputs.
 * 
 * If you already have own ActiveField class, you can use [[FieldFileInputsTrait]] trait
 * for declaring file inputs.
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class ActiveFieldEx extends ActiveField
{
    use FieldFileInputsTrait;
}
