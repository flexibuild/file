<?php

namespace flexibuild\file;

use yii\base\Model;
use yii\base\Behavior as BaseBehavior;
use yii\validators\Validator;

use flexibuild\file\contexts\ContextInterface;

/**
 * Behavior class is default class that used in [[behabiors()]] method of 
 * your model.
 * 
 * @property \yii\base\Model $owner
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class Behavior extends BaseBehavior
{
    /**
     * @var ContextInterface[] in attribute => context format.
     */
    public $contexts = array();

    /**
     * Adds beforeValidate event for the model.
     * @return array
     */
    public function events()
    {
        return [
            Model::EVENT_BEFORE_VALIDATE => 'beforeValidate',
        ];
    }

    /**
     * @var bool
     */
    private $_validatorAdded = false;

    /**
     * @param \yii\base\ModelEvent $event
     */
    public function beforeValidate($event)
    {
        if ($this->_validatorAdded) {
            return;
        }

        foreach ($this->contexts as $attribute => $context) {
            foreach ($context->getHandler()->getValidators() as $validator) {
//                $this->owner->getValidators()->append()
                $validator = Validator::createValidator($validator, $object, $attributes);
                $validator->validate($validator);
            }
        }
//        $this->owner->getValidators()->append(Validator::createValidator(, $object, $attributes, $params))
//        $this->owner->validators->unshift(somenthing here);
    }
}
