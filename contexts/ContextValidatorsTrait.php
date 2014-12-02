<?php

namespace flexibuild\file\contexts;

use yii\base\Model;
use yii\base\InvalidConfigException;
use yii\validators\Validator;
use yii\validators\FileValidator;

/**
 * Horizontally separated context's logic for validators.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
trait ContextValidatorsTrait
{
    /**
     * @var array
     */
    private $_validators;

    /**
     * Validators configuration that will be attached to the model that will use this context.
     * 
     * Each element will be an array with the following structure:
     * ```
     *      ['type', 'param1' => 'value1', 'param2' => 'value2']
     * ```
     * 
     * Validator type specifies the validator to be used.
     * It can be a built-in validator name, a method name of the model class,
     * an anonymous function, or a validator class name.
     * 
     * By default array with one element: [['yii\validators\FileValidator']],
     * that means correctly uploading (but non-required) will be validated.
     * @see [[defaultValidators()]]
     * 
     * @return array array of context's files validators.
     */
    public function getValidators()
    {
        if ($this->_validators === null) {
            $this->setValidators($this->defaultValidators());
        }
        if (!is_array($this->_validators)) {
            throw new InvalidConfigException('Method '.get_class($this).'::defaultValidators() must return an array.');
        }
        return $this->_validators;
    }

    /**
     * Sets validators configuration.
     * @param array $validators validators configuration. Each element may be one of:
     * - string, validator type;
     * - array with the following structure:
     * ```
     *      ['type', 'param1' => 'value1', 'param2' => 'value2']
     * ```
     * Validator type specifies the validator to be used.
     * It can be a built-in validator name, a method name of the model class,
     * an anonymous function, or a validator class name.
     * 
     * @throws InvalidConfigException if config has incorrect format.
     */
    public function setValidators($validators)
    {
        if (!is_array($validators)) {
            throw new InvalidConfigException('Validators param must be an array.');
        }

        foreach ($validators as $ind => $validator) {
            if (!is_array($validator)) {
                $validators[$ind] = $validator = [$validator];
            } elseif (!isset($validator[0])) {
                throw new InvalidConfigException("Each context's validator must have validator type as element with index 0 in parameters array.");
            }

            $type = $validator[0];
            if (!is_string($type) && !($type instanceof \Closure)) {
                throw new InvalidConfigException('Incorrect type of validator: '.gettype($type).'.');
            }
        }

        $this->_validators = $validators;
    }

    /**
     * Returns default context's validators configuration. This method may be
     * overrided in subclasses.
     * @return array default config for [[getValidators()]].
     * This value will be used in [[getValidators()]] if 'validators' property was not set.
     */
    protected function defaultValidators()
    {
        return [FileValidator::className()];
    }

    /**
     * Validates model file attributes by context's validators.
     * @param Model $model
     * @param mixed $attributeNames array of file attributes, string comma sepatted attribute names.
     * @return boolean whether validation is successfull without errors.
     */
    public function validate($model, $attributeNames)
    {
        if (!is_array($attributeNames)) {
            $attributeNames = preg_split('/\s*,\s*/', $attributeNames);
        }

        foreach ($this->getValidators() as $validatorCfg) {
            $validator = Validator::createValidator($validatorCfg[0], $model, $attributeNames, array_slice($validatorCfg, 1));
            $validator->validateAttributes($model, $attributeNames);
        }

        foreach ($attributeNames as $attribute) {
            if ($model->hasErrors($attribute)) {
                return false;
            }
        }
        return true;
    }
}
