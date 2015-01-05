<?php

namespace flexibuild\file\widgets;

/**
 * Trait is used in ActiveFieldEx class. 
 * But you can use the trait in you own ActiveField class for declaring file inputs.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 * 
 * @property \yii\widgets\ActiveForm $form the form that this field is associated with.
 * @property \yii\base\Model $model the data model that this field is associated with.
 * @property string $attribute the model attribute that this field is associated with.
 * @property array $parts different parts of the field (e.g. input, label). @see [[\yii\widgets\ActiveField::$parts]] for more info.
 */
trait FieldFileInputsTrait
{
    /**
     * Renders a file input as [[SimpleFileInput]] widget.
     * This method will generate the "name" and "value" tag attributes automatically for the model attribute
     * unless they are explicitly specified in `$options`.
     * @param array $options the tag options in terms of name-value pairs.
     * These options will be passed as properties of [[SimpleFileInput]].
     * @see SimpleFileInput
     */
    public function fileInput($options = [])
    {
        $options['model'] = $this->model;
        $options['attribute'] = $this->attribute;
        $options['view'] = $this->form->getView();
        $this->parts['{input}'] = SimpleFileInput::widget($options);

        return $this;
    }
}
