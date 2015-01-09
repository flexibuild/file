<?php

namespace flexibuild\file\web;

use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;

/**
 * UploadControllerTrait keeps logic of [[UploadController]].
 * You can use this trait in your own controller.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
trait UploadControllerTrait
{
    /**
     * @var array of context names. 
     * For each name the controller will declare action 'upload-{context-name}'
     * that can be used for uploading files in this context.
     * 
     * You can defaine any action params for each of contexts.
     * 
     * For example:
     * ```php
     *  'contexts' => [
     *      'default',
     *      'product' => [
     *          'param1' => 'value1',
     *          'param2' => 'value2',
     *      ],
     *  ],
     * ```
     */
    public $contexts = [];

    /**
     * @var string default action class.
     */
    public $defaultActionClass = 'flexibuild\file\web\UploadAction';

    /**
     * @var array default params for each of contexts actions.
     */
    public $defaultActionParams = [];

    /**
     * @inheritdoc
     */
    public function actions()
    {
        $result = [];

        foreach ($this->contexts as $key => $value) {
            if (is_int($key)) {
                $contextName = $value;
                $params = [];
            } else {
                $contextName = $key;
                $params = is_array($value) ? $value : ['class' => $value];
            }

            $actionName = Inflector::camel2id('upload' . ucfirst($contextName));
            $result[$actionName] = ArrayHelper::merge($this->defaultActionParams, [
                'class' => $this->defaultActionClass,
                'context' => $contextName,
            ], $params);
        }

        return $result;
    }
}
