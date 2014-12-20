<?php

namespace flexibuild\file;

use Yii;
use yii\base\Behavior;
use yii\base\Event;
use yii\base\Model;
use yii\base\ModelEvent;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;
use yii\base\InvalidValueException;
use yii\web\UploadedFile;
use yii\validators\Validator;

use flexibuild\file\contexts\Context;
use flexibuild\file\events\DataChangedEvent;

/**
 * Behavior that must be attached to model for using flexibuild/file logic on models.
 * 
 * Example:
 * @todo fill examples
 * ```
 *  
 * ```
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 * 
 * @property Context[] $attributes file data attributes that used in the model with them contexts.
 * You can set contexts names without it objects, for example:
 * ```php
 *  'attributes' => [
 *      'avatar' => 'user', // means use Yii::$app->contextManager->getContext('user'),
 *      'image' => Yii::$app->contextManager->getContext('product'),
 *  ],
 * ```
 */
class ModelBehavior extends Behavior
{
    /**
     * File data attributes that used in the model with them contexts.
     * @var Context[] array in attribute name => Context (or context name) format.
     */
    private $_attributes = [];

    /**
     * @var string the name of contexts manager application component.
     * It will be used if any context name will be passed in `$attributes` property.
     */
    public $manager = 'contextManager';

    /**
     * By default the behavior fills file attributes as source attributes
     * (keys of param `$attributes`) with 'File' suffix, e.g.:
     * 
     * - `$image` is source field with image file data, that key represented in `$attributes`
     * - `$imageFile` is File object
     * 
     * You may to change this behavior by setting your own map for some of attributes.
     * 
     * @var array attributes map in file object field name => data field. For example:
     * ```
     *  [
     *      'attributes' => [
     *          'image_data' => 'user',
     *          'pdf' => 'document',
     *      ],
     *      'fileAttributesMap' => [
     *          'avatarFile' => 'image_data',
     *      ],
     *  ]
     * ```
     * 
     * In this example:
     * - `$model->avatarFile` is link to file attribute object that data is stored in 'image_data' attribute.
     * - `$model->pdfFile` is link to file attribute object that data is stored in 'pdf' attribute.
     */
    public $fileAttributesMap = [];

    /**
     * @var File[] property that keeps has been already initialized file objects.
     */
    private $_files = [];

    /**
     * Keeps old data attributes values.
     * @var array in data attribute name => data value format.
     */
    private $_oldFilesDatas = [];

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        parent::attach($owner);
        $this->initFileAttributesMap();
        $this->saveOldDataAttributes();
    }

    /**
     * @inheritdoc
     */
    public function events()
    {
        return array_merge(parent::events(), [
            'init' => 'afterInit',
            'beforeValidate' => 'beforeValidate',
            'afterValidate' => 'afterValidate',
            'afterFind' => 'afterFind',
            'beforeInsert' => 'beforeInsert',
            'afterInsert' => 'afterInsert',
            'beforeUpdate' => 'beforeUpdate',
            'afterUpdate' => 'afterUpdate',
            'beforeDelete' => 'beforeDelete',
            'afterDelete' => 'afterDelete',
        ]);
    }

    /**
     * @inheritdoc
     * Magic PHP method. Besides parent logic the method adds magic properties `as{Format}`.
     */
    public function __get($name)
    {
        if (!parent::canGetProperty($name, false) && $this->hasFileAttribute($name)) {
            return $this->getFileAttribute($name);
        }
        return parent::__get($name);
    }

    /**
     * @inheritdoc
     * Magic PHP method. Besides parent logic the method adds magic properties `as{Format}`.
     */
    public function __set($name, $value)
    {
        if (!parent::canSetProperty($name, false) && $this->hasFileAttribute($name)) {
            $this->setFileAttribute($name, $value);
            return;
        }
        parent::__set($name, $value);
    }

    /**
     * @inheritdoc
     * Magic PHP method. Besides parent logic the method adds magic properties `as{Format}`.
     */
    public function __isset($name)
    {
        if (!parent::canGetProperty($name, false) && $this->hasFileAttribute($name)) {
            return true;
        }
        return parent::__isset($name);
    }

    /**
     * @inheritdoc
     * Magic PHP method. Besides parent logic the method adds magic properties `as{Format}`.
     */
    public function __unset($name)
    {
        if (!parent::hasProperty($name, false) && $this->hasFileAttribute($name)) {
            throw new InvalidCallException('Unsetting file attribute '.get_class($this->owner)."::$name.");
        }
        parent::__unset($name);
    }

    /**
     * @inheritdoc
     * Besides parent logic the method adds magic properties `as{Format}` logic.
     */
    public function canGetProperty($name, $checkVars = true)
    {
        return $this->hasFileAttribute($name) || parent::canGetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     * Besides parent logic the method adds magic properties `as{Format}` logic.
     */
    public function canSetProperty($name, $checkVars = true)
    {
        return $this->hasFileAttribute($name) || parent::canSetProperty($name, $checkVars);
    }


    /**
     * This method will be executed on 'init' active record event.
     * @param Event $event triggered event.
     */
    public function afterInit($event)
    {
        // nothing to do
    }

    /**
     * This method will be executed on 'beforeValidate' model event.
     * @param ModelEvent $event triggered event.
     */
    public function beforeValidate($event)
    {
        // nothing to do
    }

    /**
     * This method will be executed on 'afterValidate' model event.
     * @param Event $event triggered event.
     */
    public function afterValidate($event)
    {
        // Validating takes place in afterValidate since if validated in beforeValidate
        // we get the following security issue:
        // If we add validators in beforeValidate and some event beforeValidate handler there
        // after returns valid == false then these validators will remain in the model.
        // As a result, all file attributes will be considered safe and can be changed through setAttributes.
        // Choosing between security and performance choice was made on security.
        // Smaller perfomance means that all file attributes will be validated
        // even if they was not passed in $model->validate($attribute) method through $attributes param.
        $this->validateFileAttributes();
        $this->saveUploadedFiles(false);
    }

    /**
     * This method will be executed on 'afterFind' active record event.
     * @param Event $event triggered event.
     */
    public function afterFind($event)
    {
        $this->saveOldDataAttributes();
    }

    /**
     * This method will be executed on 'beforeInsert' active record event.
     * @param ModelEvent $event triggered event.
     */
    public function beforeInsert($event)
    {
        $this->saveUploadedFiles(false);
    }

    /**
     * This method will be executed on 'afterInsert' active record event.
     * @param Event $event triggered event.
     */
    public function afterInsert($event)
    {
        $this->saveOldDataAttributes();
    }

    /**
     * This method will be executed on 'beforeUpdate' active record event.
     * @param ModelEvent $event triggered event.
     */
    public function beforeUpdate($event)
    {
        $this->saveUploadedFiles(false);
    }

    /**
     * This method will be executed on 'afterUpdate' active record event.
     * @param Event $event triggered event.
     */
    public function afterUpdate($event)
    {
        $this->saveOldDataAttributes();
    }

    /**
     * This method will be executed on 'beforeDelete' active record event.
     * @param ModelEvent $event triggered event.
     */
    public function beforeDelete($event)
    {
        // nothing to do
    }

    /**
     * This method will be executed on 'afterDelete' active record event.
     * @param Event $event triggered event.
     */
    public function afterDelete($event)
    {
        // nothing to do
    }

    /**
     * Initializes [[self::$fileAttributeMap]] property.
     * The method fills this property by file attributes that excepted in it before.
     * @throws InvalidConfigException
     */
    protected function initFileAttributesMap()
    {
        $flippedMap = array_flip($this->fileAttributesMap); // keys - unique data attributes
        foreach (array_keys($this->_attributes) as $dataAttribute) {
            if (isset($flippedMap[$dataAttribute])) {
                continue;
            }

            $fileAttribute = "{$dataAttribute}File";
            if (isset($this->fileAttributesMap[$fileAttribute])) {
                throw new InvalidConfigException(
                    "File data attribute '" . $dataAttribute . "'" .
                    " is not mapped with any file object attribute," .
                    " but '$fileAttribute' is already mapped with" .
                    " '" . $this->fileAttributesMap[$fileAttribute] . "'."
                );
            }
            $this->fileAttributesMap[$fileAttribute] = $dataAttribute;
        }
    }

    /**
     * Returns whether current behavior has file property.
     * @param string $name name of property that must be checked.
     * @return boolean whether the behavior has file property.
     */
    public function hasFileAttribute($name)
    {
        return isset($this->fileAttributesMap[$name]) &&
            isset($this->_attributes[$this->fileAttributesMap[$name]]);
    }

    /**
     * Returns list of all file attributes names.
     * @return array array of all file attributes names.
     */
    public function fileAttributes()
    {
        return array_keys($this->fileAttributesMap);
    }

    /**
     * Returns attributes config in data attribute name => Context object.
     * @return Context[] file data attributes that used in the model with them contexts.
     */
    public function getAttributes()
    {
        foreach ($this->_attributes as $dataAttribute => $context) {
            $this->_attributes[$dataAttribute] = $this->fetchAttributeContext($context);
        }
        return $this->_attributes;
    }

    /**
     * Sets attributes config in data attribute name => Context object.
     * @param array $attributes file data attributes that used in the model with them contexts.
     * You can set contexts names without it objects, for example:
     * ```php
     *  'attributes' => [
     *      'avatar' => 'user', // means use Yii::$app->contextManager->getContext('user'),
     *      'image' => Yii::$app->contextManager->getContext('product'),
     *  ],
     * ```
     * @throws InvalidConfigException if $attributes has invalid format.
     */
    public function setAttributes($attributes)
    {
        if (!is_array($attributes)) {
            throw new InvalidConfigException('Param $attributes must be an array.');
        }
        $this->_attributes = $attributes;
    }

    /**
     * Checks if `$context` is not an instance of [[Context]] than gets it by name from context manager.
     * @param string|Context $context context object or it name.
     * @return Context the context object.
     */
    protected function fetchAttributeContext($context)
    {
        if (!$context instanceof Context) {
            $contextManager = Yii::$app->get($this->manager);
            /* @var $contextManager ContextManager */
            $context = $contextManager->getContext($context);
        }
        return $context;
    }

    /**
     * Returns context for the file attribute.
     * @param string $name name of file property.
     * @return Context context for current file attribute.
     * @throws InvalidCallException if the object has not file attribute with this name.
     */
    public function getFileContext($name)
    {
        $dataAttribute = $this->getFileDataAttribute($name);
        $context = $this->_attributes[$dataAttribute];
        $this->_attributes[$dataAttribute] = $context = $this->fetchAttributeContext($context);
        return $context;
    }

    /**
     * Returns the name of attribute, that keeps data value for the `$name` file attribute.
     * @param string $name
     * @return string the name of file data attribute.
     * @throws InvalidCallException if the object has not file attribute with this name.
     */
    public function getFileDataAttribute($name)
    {
        if (!$this->hasFileAttribute($name)) {
            throw new InvalidCallException("The model has not file attribute with name '$name'.");
        }
        return $this->fileAttributesMap[$name];
    }

    /**
     * Returns file object for the `$name` file attribute.
     * @param string $name the name of file attribute.
     * @return File the file object.
     * @throws InvalidCallException if the object has not file attribute with this name.
     */
    public function getFileAttribute($name)
    {
        $dataAttribute = $this->getFileDataAttribute($name);

        if (isset($this->_files[$dataAttribute])) {
            return $this->_files[$dataAttribute];
        }

        $file = $this->createFile($name);
        $file->on(File::EVENT_DATA_CHANGED, [$this, 'changeDataAttribute'], [
            'model' => $this->owner,
            'attribute' => $dataAttribute,
        ]);
        return $this->_files[$dataAttribute] = $file;
    }

    /**
     * Sets value into file attribute object.
     * @param string $name the name of file attribute.
     * @param mixed $value value may be one of this:
     * 
     * - null, for empty files,
     * - string, value that will be parsed by [[Context::parseInputValue()]] method,
     * - an instance of [[UploadedFile]].
     * 
     * @throws InvalidValueException if [[Context::parseInputValue()]] will return unexpected value.
     * @throws InvalidCallException if the object has not file attribute with this name.
     */
    public function setFileAttribute($name, $value)
    {
        $file = $this->getFileAttribute($name);
        if ($file === $value) {
            return;
        }
        $context = $file->context;
        $value = $context->parseInputValue($value);

        if ($value === null) {
            $file->clearFile();
        } elseif (is_string($value)) {
            $file->populateFileData($value);
        } elseif ($value instanceof UploadedFile) {
            $file->populateFromUploadedFile($value);
        } else {
            throw new InvalidValueException('Method '.get_class($context).'::parseInputValue() returned unexpected value.');
        }
    }

    /**
     * Handler for [[DataChangedEvent]], changes the value of source data attribute.
     * @param DataChangedEvent $event
     */
    public function changeDataAttribute($event)
    {
        $model = $event->data['model'];
        $attribute = $event->data['attribute'];
        $model->{$attribute} = $event->newFileData;
    }

    /**
     * Creates new File object.
     * @param string $name the name of file attribute for which file must be created.
     * @return File created file instance.
     */
    protected function createFile($name)
    {
        $dataAttribute = $this->getFileDataAttribute($name);
        $context = $this->getFileContext($name);
        $data = $this->owner->{$dataAttribute};

        $file = $context->createFile();
        $file->dataAttribute = $dataAttribute;
        $file->owner = $this;
        if ($data === null) {
            $file->clearFile();
        } else {
            $file->populateFileData($data);
        }

        return $file;
    }

    /**
     * Saves old data attributes values to [[$_oldFilesDatas]] property.
     */
    protected function saveOldDataAttributes()
    {
        foreach (array_keys($this->_attributes) as $dataAttribute) {
            $this->_oldFilesDatas[$dataAttribute] = $this->owner->{$dataAttribute};
        }
    }

    /**
     * Returns old file attribute data value.
     * @param string file attribute name.
     * @return string old file data attribute value
     * @throws InvalidCallException if the object has not file attribute with this name.
     */
    public function getOldDataAttributeValue($name)
    {
        $dataAttribute = $this->getFileDataAttribute($name);
        return isset($this->_oldFilesDatas[$dataAttribute])
            ? $this->_oldFilesDatas[$dataAttribute]
            : null;
    }

    /**
     * Property keeps created validators.
     * @var Validator[]|null
     */
    private $_validators;

    /**
     * @param array $attributeNames list of file attribute names that should be validated.
     * If this parameter is empty, it means all file attributes should be validated.
     * @return boolean whether file attributes validation is successfull without any error.
     * @throws InvalidValueException if the behavior has invalid owner.
     */
    public function validateFileAttributes($attributeNames = null)
    {
        if (!($owner = $this->owner) || !($owner instanceof Model)) {
            throw new InvalidValueException(get_class($this).' must be attached to an instance of '.Model::className().'.');
        }

        if ($this->_validators === null) {
            $this->_validators = $this->createValidators();
        }

        $scenario = $owner->getScenario();
        foreach ($this->_validators as $validator) {
            if ($validator->isActive($scenario)) {
                $validator->validateAttributes($owner, $attributeNames);
            }
        }

        $attributeNames = is_array($attributeNames) ? $attributeNames : $this->fileAttributes();
        foreach ($attributeNames as $attribute) {
            if ($owner->hasErrors($attribute)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Creates and returns list of all file attributes validators.
     * @return Validator[] list validators.
     * @throws InvalidValueException if the behavior has invalid owner.
     */
    public function createValidators()
    {
        if (!($owner = $this->owner) || !($owner instanceof Model)) {
            throw new InvalidValueException(get_class($this).' must be attached to an instance of '.Model::className().'.');
        }

        $contexts = new \SplObjectStorage();
        foreach ($this->fileAttributes() as $fileAttribute) {
            $context = $this->getFileContext($fileAttribute);
            $fileAttributes = $contexts->offsetExists($context) ? $contexts->offsetGet($context) : [];
            $fileAttributes[] = $fileAttribute;
            $contexts->offsetSet($context, $fileAttributes);
        }

        $result = [];
        $contexts->rewind();
        while ($contexts->valid()) {
            $context = $contexts->current();
            $fileAttributes = $contexts->getInfo();

            foreach ($context->getValidators() as $validator) {
                $validator = Validator::createValidator($validator[0], $owner, $fileAttributes, array_slice($validator, 1, null, true));
                $result[] = $validator;
            }

            $contexts->next();
        }

        return $result;
    }

    /**
     * Saves uploaded files.
     * @param boolean $runValidation whether the validation must be called before saving.
     * @param array|null $attributeNames list of file attribute names that need to be saved. Defaults to null,
     * meaning all attributes that are loaded from DB will be saved.
     * @throws InvalidValueException if the behavior has invalid owner or any file cannot be saved.
     */
    public function saveUploadedFiles($runValidation = true, $attributeNames = null)
    {
        if (!($owner = $this->owner) || !($owner instanceof Model)) {
            throw new InvalidValueException(get_class($this).' must be attached to an instance of '.Model::className().'.');
        }

        if ($runValidation) {
            $this->validateFileAttributes($attributeNames);
        }

        $attributeNames = is_array($attributeNames) ? array_fill_keys($attributeNames, true) : null;
        foreach ($this->fileAttributes() as $fileAttribute) {
            if ($attributeNames !== null && !isset($attributeNames[$fileAttribute])) {
                continue;
            }

            $dataAttribute = $this->getFileDataAttribute($fileAttribute);
            $file = $this->getFileAttribute($fileAttribute);

            if ($owner->hasErrors($fileAttribute) || $owner->hasErrors($dataAttribute)) {
                continue;
            }

            if ($file->status !== File::STATUS_UPLOADED_FILE) {
                continue;
            }
            if (!$file->save()) {
                throw new InvalidValueException("Cannot save file '$file->name' of the '$fileAttribute' attribute.");
            }
        }
    }
}
