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
use yii\helpers\StringHelper;
use yii\validators\Validator;
use yii\validators\FileValidator;

use flexibuild\file\contexts\Context;
use flexibuild\file\events\CannotGetUrlEvent;
use flexibuild\file\events\CannotGetUrlHandlers;
use flexibuild\file\events\DataChangedEvent;

/**
 * Behavior that must be attached to model for using flexibuild/file logic on models.
 * 
 * Example:
 * ```!!!
 *  
 * ```
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class ModelBehavior extends Behavior
{
// !!! todo: allowDeletion and prevent replacing alien storage datas.
    /**
     * File attributes that used in the model with them contexts.
     * 
     * For example !!!
     * 
     * @var Context[] array in attribute name => Context format.
     */
    public $attributes = [];

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
     *          'image_data' => Yii::!!!,
     *          'pdf' => Yii::!!!,
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
     * !!!
     * @var File[]
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
     * !!!
     * @param type $name
     * @return type
     */
    public function __get($name)
    {
        if (!parent::canGetProperty($name, false) && $this->hasFileAttribute($name)) {
            return $this->getFileAttribute($name);
        }
        return parent::__get($name);
    }

    /**
     * !!!
     * @param type $name
     * @param type $value
     */
    public function __set($name, $value)
    {
        if (!parent::canSetProperty($name, false) && $this->hasFileAttribute($name)) {
            $this->setFileAttribute($name, $value);
            return;
        }
        parent::__set($name, $value);
    }

    // !!!
    public function __isset($name)
    {
        if (!parent::canGetProperty($name, false) && $this->hasFileAttribute($name)) {
            return true;
        }
        return parent::__isset($name);
    }

    // !!!
    public function __unset($name)
    {
        if (!parent::hasProperty($name, false) && $this->hasFileAttribute($name)) {
            throw new InvalidCallException('Unsetting file attribute '.get_class($this->owner)."::$name.");
        }
        parent::__unset($name);
    }

    /**
     * @inheritdoc
     * !!!
     */
    public function canGetProperty($name, $checkVars = true)
    {
        return $this->hasFileAttribute($name) || parent::canGetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     * !!!
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
        // Saving takes place in afterValidate since if validated in beforeValidate
        // we get the following security issue:
        // If we add validators in beforeValidate and some event beforeValidate handler there
        // after returns valid == false then these validators will remain in the model.
        // As a result, all file attributes will be considered safe and can be changed through setAttributes.
        // Choosing between security and performance choice was made on security.
        $this->validateFileAttributes();
        $this->saveUploadedFiles();
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
        $this->saveUploadedFiles();
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
        $this->saveUploadedFiles();
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
     * !!!
     * @throws InvalidConfigException
     */
    protected function initFileAttributesMap()
    {
        $flippedMap = array_flip($this->fileAttributesMap); // keys - unique data attributes
        foreach ($this->attributes as $dataAttribute => $context) {
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
            isset($this->attributes[$this->fileAttributesMap[$name]]);
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
     * Returns context for the file attribute.
     * @param string $name name of file property.
     * @return Context context for current file attribute.
     * @throws InvalidCallException if the object has not file attribute with this name.
     */
    public function getFileContext($name)
    {
        $dataAttribute = $this->getFileDataAttribute($name);
        return $this->attributes[$dataAttribute];
    }

    /**
     * !!!
     * @param string $name
     * @return string
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
     * This property is needed because one data attribute may be assigned with several file attributes.
     * @var array[] keeps array handled file attributes in format: data attribute => array of file attributes.
     */
    private $_handledFileAttributes = [];

    /**
     * !!!
     * @param string $name
     * @throws InvalidCallException if the object has not file attribute with this name.
     */
    public function getFileAttribute($name)
    {
        $dataAttribute = $this->getFileDataAttribute($name);

        if (isset($this->_files[$dataAttribute])) {
            $file = $this->_files[$dataAttribute];
        } else {
            $this->_files[$dataAttribute] = $file = $this->getFileContext($name)->createFile();
            $file->owner = $this;
            $file->dataAttribute = $dataAttribute;
        }

        if (!isset($this->_handledFileAttributes[$dataAttribute][$name])) {
            $file->getHandler()->on(FileHandler::EVENT_DATA_CHANGED, [$this, 'changeDataAttribute'], [
                'model' => $this->owner,
                'attribute' => $dataAttribute,
            ]);
            $this->_handledFileAttributes[$dataAttribute][$name] = true;
        }

        return $file;
    }

    /**
     * !!!
     * @param string $name
     * @param mixed $value
     * @throws InvalidValueException
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
     * !!!
     * @param string $name
     * @return File
     */
    protected function instantiateFile($name)
    {
        $dataAttribute = $this->getFileDataAttribute($name);
        $context = $this->getFileContext($name);
        $data = $this->owner->{$dataAttribute};

        $file = $context->instantiateFile();
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
        foreach ($this->attributes as $dataAttribute => $context) {
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
     * !!!
     * @var Validator[]|null
     */
    private $_validators;

    /**
     * !!!
     */
    public function validateFileAttributes()
    {
        if (!($owner = $this->owner) || !($owner instanceof Model)) {
            return;
        }

        $fileAttributes = $this->fileAttributes();
        if ($this->_validators === null) {
            $this->_validators = $this->createValidators();
        }

        $scenario = $owner->getScenario();
        foreach ($this->_validators as $validator) {
            if ($validator->isActive($scenario)) {
                $validator->validateAttributes($owner, [$fileAttributes]);
            }
        }
    }

    /**
     * Creates and returns list of all file attributes validators.
     * @return Validator[] list validators.
     */
    public function createValidators()
    {
        if (!($owner = $this->owner) || !($owner instanceof Model)) {
            return [];
        }

        $result = [];
        foreach ($this->fileAttributes() as $fileAttribute) {
            $context = $this->getFileContext($fileAttribute);
            foreach ($context->getValidators() as $validator) {
                $validator = Validator::createValidator($validator[0], $owner, [$fileAttribute], array_slice($validator, 1));
                $result[] = $validator;
            }
        }
        return $result;
    }

    /**
     * Saves all uploaded files.
     * @throws InvalidValueException if any file cannot be saved.
     */
    public function saveUploadedFiles()
    {
        if (!($owner = $this->owner) || !($owner instanceof Model)) {
            return;
        }

        foreach ($this->fileAttributes() as $fileAttribute) {
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
