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
 * @property \yii\validators\Validator[] $validators for validating files.
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class DefaultHandler extends Component implements HandlerInterface
{
    /**
     * @var mixed
     */
    private $_validators;

    /**
     * Gets file validators.
     * @return \yii\validators\Validator[]
     */
    public function getValidators()
    {
        if ($this->_validators === null) {
            $this->_validators = $this->defaultValidators();
        }

        foreach ($this->_validators as $key => $validator) {
            if (!is_object($validator)) {
                $this->_validators[$key] = Yii::createObject($validator);
            }
        }

        return $this->_validators;
    }

    /**
     * Sets handler validators. You can use Yii standarts for setting validators
     * configuration.
     * @param array $validators
     */
    public function setValidators($validators)
    {
        $this->_validators = $validators;
    }

    /**
     * Returns default handler's validators configuration. This method may be
     * overrided in subclasses.
     * @return array default validators config. Used in `getValidators()` if validators
     * was not set.
     */
    protected function defaultValidators()
    {
        return [FileValidator::className()];
    }
}
