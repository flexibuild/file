<?php

namespace flexibuild\file\widgets\bootstrap;

use yii\bootstrap\ActiveField;
use flexibuild\file\widgets\FieldFileInputsTrait;

/**
 * ActiveFieldEx class extends [[\yii\bootstrap\ActiveField]] class with different file typed intputs.
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
