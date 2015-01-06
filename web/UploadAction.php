<?php

namespace flexibuild\file\web;

use Yii;
use yii\base\Action;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;
use yii\base\DynamicModel;
use yii\web\Response;
use yii\web\Request;
use yii\web\UploadedFile;

use flexibuild\file\ContextManager;

/**
 * UploadAction is web action that saves file to storage.
 * It can be used for an ajax file uploaders.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 * 
 * @property-read Response $response web application response object.
 * @property-read Request $request web application request object.
 * @property-read ContextManager $contextManager the context manager component.
 */
class UploadAction extends Action
{
    /**
     * @var string the name of context.
     */
    public $context;

    /**
     * @var string the name of ContextManager component.
     */
    public $managerComponent = 'contextManager';

    /**
     * @var string the name of POST param with file.
     */
    public $postName = 'file';

    /**
     * @var mixed value that will be passed as `$scheme` parameter when file urls will be generated.
     */
    public $urlsScheme = false;

    /**
     * @var string message that will be returned if file was not uploaded.
     */
    public $notUploadedMessage = 'File was not uploaded.';

    /**
     * @var string message that will be returned if file was not saved.
     */
    public $notSavedMessage = 'File was not saved.';

    /**
     * UploadAction logic.
     */
    public function run()
    {
        $this->getResponse()->format = Response::FORMAT_JSON;
        $context = $this->getContextManager()->getContext($this->context);

        // parse POST data
        $value = $context->postParamUploadPrefix . $this->postName;
        $uploadedFile = $context->parseInputValue($value);
        if (!$uploadedFile instanceof UploadedFile) {
            return $this->returnFileNotUploaded();
        }

        // create file object, validate and save it
        $file = $context->createFile();
        $file->populateFromUploadedFile($uploadedFile);
        $model = new DynamicModel(['file' => $file]);

        if (!$context->validate($model, ['file'])) {
            return $this->returnFileValidateError($model, 'file');
        } elseif (!$file->save()) {
            return $this->returnFileNotSaved();
        }

        return [
            'success' => true,
            'file' => static::convertFileToArray($file, $this->urlsScheme),
        ];
    }

    /**
     * Returns response for case when file was not uploaded.
     * @return array array of json response.
     */
    protected function returnFileNotUploaded()
    {
        return [
            'success' => false,
            'message' => $this->notUploadedMessage,
        ];
    }

    /**
     * Returns response for case when file was not saved.
     * @param \yii\base\Model $model the model that has been validated.
     * @param string $attribute the name of validated attribute.
     * @return array array of json response.
     */
    protected function returnFileValidateError($model, $attribute)
    {
        return [
            'success' => false,
            'message' => $model->getFirstError($attribute),
        ];
    }

    /**
     * Returns response for case when file was not saved.
     * @return array array of json response.
     */
    protected function returnFileNotSaved()
    {
        return [
            'success' => false,
            'message' => $this->notSavedMessage,
        ];
    }

    /**
     * Converts File object to array for returning in json responses.
     * @param \flexibuild\file\File $file the file object.
     * @param boolean $scheme scheme value that will be used for getting file urls.
     * @return array array of file data that will be return in response.
     */
    public static function convertFileToArray($file, $scheme = false)
    {
        $formats = [];
        foreach ($file->getFormatList() as $format) {
            $formats[$format] = $file->getUrl($format, $scheme);
        }

        $result = [
            'value' => $file->getData(),

            'url' => $file->getUrl(null, $scheme),
            'formats' => $formats,

            'name' => $file->getName(),
            'size' => $file->getSize(),
            'ext' => $file->getExtension(),
            'type' => $file->getType(),
        ];

        return $result;
    }

    /**
     * @inheritdoc
     * @throws InvalidCallException if it is used in non-web application.
     * @throws InvalidConfigException if context was not found or incorrect context manager component.
     */
    protected function beforeRun()
    {
        if (!parent::beforeRun()) {
            return false;
        }

        if (!$this->getResponse() instanceof Response || !$this->getRequest() instanceof Request) {
            throw new InvalidCallException(static::className() . ' can be used with web applications only.');
        }

        if (!$this->getContextManager() instanceof ContextManager) {
            if (is_string($this->managerComponent)) {
                throw new InvalidConfigException("Incorrect context manager component name '$this->contextManager'.");
            } else {
                throw new InvalidConfigException('Incorrect type of $contextManager property: ' . gettype($this->contextManager) . '.');
            }
        }
        if (!$this->getContextManager()->hasContext($this->context)) {
            throw new InvalidConfigException("Unknown context name: '$this->context'.");
        }

        return true;
    }

    /**
     * @return Response web application response object.
     */
    public function getResponse()
    {
        return Yii::$app->get('response');
    }

    /**
     * @return Request web application response object.
     */
    public function getRequest()
    {
        return Yii::$app->get('request');
    }

    /**
     * @return ContextManager the ContextManager component.
     */
    public function getContextManager()
    {
        return Yii::$app->get($this->managerComponent);
    }
}
