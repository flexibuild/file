<?php

namespace flexibuild\file;

use yii\base\Model;
use yii\base\Behavior as BaseBehavior;

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
     * @param \yii\base\ModelEvent $event
     */
    public function beforeValidate($event)
    {
//        $this->owner->validators->unshift(somenthing here);
    }
}
