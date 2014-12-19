<?php

namespace flexibuild\file\widgets;

use yii\widgets\InputWidget;
use yii\helpers\Html;
use yii\base\InvalidConfigException;

use flexibuild\file\File;

/**
 * @author SeynovAM <sejnovalexey@gmail.com>
 * 
 * @property-read string $inputId the id of file input.
 */
class SimpleFile extends InputWidget
{
    use FormEnctypeTrait;

    /**
     * @var string the name of format link which must be rendered when file has been already uploaded.
     * Null (default) meaning link to the source file will be rendered.
     */
    public $linkFormat = null;

    /**
     * @var string the scheme for link which must be rendered when file has been already uploaded:
     *
     * - `false` (default): generating a relative URL.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::hostInfo]].
     * - string: generating an absolute URL with the specified scheme (either `http` or `https`).
     * 
     */
    public $linkScheme = false;

    /**
     * @var array html options of link. One target '_blank' property by default.
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

        $model = $this->model;
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
        $link = $this->renderLink();
        $input = $this->renderInput();
        $this->registerChangeEnctypeScripts($this->getInputId());

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

    /**
     * Renders link html, if file has been already uploaded.
     * It renders link (tag 'a') if file with [[self::$linkFormat]] exists,
     * otherwise the method tries to render tag 'span' with file name.
     * @return string|null rendered link or span. Null meaning file has not been uploaded yet 
     * or link does not exists.
     */
    public function renderLink()
    {
        if ($this->_file()->exists($this->linkFormat)) {
            return Html::a(Html::encode($this->_file()->getName()), $this->_file()->getUrl($this->linkFormat, $this->linkScheme), $this->linkOptions);
        } elseif ($this->linkFormat !== null && $this->_file()->exists()) {
            return Html::tag('span', Html::encode($this->_file()->getName()));
        }
        return null;
    }

    /**
     * Renders inputs for file. The method renders hidden and file inputs.
     * Hidden input is need for keeping attribute POST name and current file storage data.
     * File input is need for possibillity to change existing file.
     * @return string the rendered inputs content.
     */
    public function renderInput()
    {
        $options = $this->options;
        $name = isset($options['name']) ? $options['name'] : Html::getInputName($this->model, $this->attribute);

        $result = Html::activeHiddenInput($this->model, $this->attribute, [
            'name' => $name,
            'value' => $this->_context()->postParamUploadPrefix . $name .
                $this->_context()->postParamStoragePrefix . $this->_file()->getData(),
        ]);

        $options['name'] = $name;
        $options['value'] = false;
        $options['id'] = $this->getInputId();
        $result .= Html::activeInput('file', $this->model, $this->attribute, $options);

        return $result;
    }

    /**
     * Returns html id attribute value for input file tag.
     * @return string the id of file input.
     */
    public function getInputId()
    {
        return isset($this->options['id']) ? $this->options['id'] : Html::getInputId($this->model, $this->attribute);
    }

    /**
     * Method-helper for retreiving File object.
     * @return File the File object.
     */
    private function _file()
    {
        return $this->model->{$this->attribute};
    }

    /**
     * Method-helper for retreiving Context object.
     * @return \flexibuild\file\contexts\Context the context object.
     */
    private function _context()
    {
        return $this->_file()->context;
    }
}
