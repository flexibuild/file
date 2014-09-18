<?php

namespace flexibuild\file\handlers;

use Yii;
use yii\base\Component;

use flexibuild\file\validators\FileValidator;

/**
 * Default handler validates and saves files.
 * 
 * If you need to save any other files on fly you may override this class and
 * implement your own logic.
 * 
 * @property \yii\validators\Validator[] $validators
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class DefaultHandler extends Component
{
    private $_validators;

    public function init()
    {
        parent::init();

    }

    /**
     * @return \yii\validators\Validator[]
     */
    public function getValidators()
    {
        if ($this->_validators === null) {
            $this->_validators = [
                FileValidator::className(),
            ];
        }

        foreach ($this->_validators as $key => $validator) {
            if (!is_object($validator)) {
                $this->_validators[$key] = Yii::createObject($validator);
            }
        }

        return $this->_validators;
    }

    /**
     * @param array $validators
     */
    public function setValidators($validators)
    {
        $this->_validators = $validators;
    }
}
