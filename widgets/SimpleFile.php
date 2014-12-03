<?php

namespace flexibuild\file\widgets;

use yii\widgets\InputWidget;
use yii\helpers\Html;
use yii\base\InvalidConfigException;

use flexibuild\file\File;

/**
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class SimpleFile extends InputWidget
{
    /**
     * @var string the name of format link on which must be rendered when file has been already uploaded.
     * Null meaning link to the source file will be rendered.
     */
    public $linkFormat = null;

    /**
     * @var array html options of link.
     */
    public $linkOptions = [
        'target' => '_blank',
    ];

    /**
     * @var string template that used for rendering file input elements.
     * You can use {link} and {input} placeholders there.
     */
    public $template = "{link}\n{input}";

    /**
     * @var string template that used when file is empty (without link).
     * Null meaning `$template` will be used with replacing {link} as ''.
     * You can use {input} placeholder only there.
     */
    public $emptyFileTemplate;

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        if (!$this->hasModel()) {
            throw new InvalidConfigException('Params $model and $attribute are required for '.get_class($this).'.');
        }

        $model = $this->hasModel();
        $file = $model->{$this->attribute};
        if (!$file instanceof File) {
            throw new InvalidConfigException("Attribute $this->attribute of ".get_class($model).' must be an instance of '.File::className().' for usage in '.get_class($this).'.');
        }
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $file = $this->model->{$this->attribute};
        /* @var $file File */
        $context = $file->context;
        /* @var $context \flexibuild\file\contexts\Context */

        $link = null;
        if ($file->exists($this->linkFormat)) {
            $scheme = isset($this->linkOptions['scheme']) ? $this->linkOptions['scheme'] : null;
            unset($this->linkOptions['scheme']);
            $link = Html::a(Html::encode($file->getName()), $file->getUrl($this->linkFormat, $scheme), $this->linkOptions);
        } elseif ($this->linkFormat !== null && $file->exists()) {
            $link = Html::tag('span', Html::encode($file->getName()));
        }

        $name = isset($this->options['name']) ? $this->options['name'] : Html::getInputName($this->model, $this->attribute);
        $this->options['name'] = $name;
        $this->options['value'] = $context->postParamUploadPrefix . $name;

        $input = Html::activeHiddenInput($this->model, $this->attribute, [
            'name' => $name,
            'value' => $context->postParamStoragePrefix . $file->getData(),
        ]);
        $input .= Html::activeInput('file', $this->model, $this->attribute, $this->options);

        if ($link === null && $this->emptyFileTemplate !== null) {
            echo strtr($this->emptyFileTemplate, [
                '{input}' => $input,
            ]);
        } else {
            echo strtr($this->template, [
                '{link}' => $link,
                '{input}' => $input,
            ]);
        }
    }
}
