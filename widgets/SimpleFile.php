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
     * Constants that may be used as `$previewType` property value.
     */
    const PREVIEW_TYPE_SPAN = 'span';
    const PREVIEW_TYPE_LINK = 'link';
    const PREVIEW_TYPE_IMAGE = 'image';
    const PREVIEW_TYPE_IMAGE_LINK = 'imageLink';

    /**
     * Type of file preview.
     * @var string file preview type. May be one of the followings:
     * 
     * - 'span' or [[self::PREVIEW_TYPE_SPAN]], span tag with file name will be rendered,
     * - 'link' or [[self::PREVIEW_TYPE_LINK]], link tag with url to the file will be rendered,
     * - 'image' or [[self::PREVIEW_TYPE_IMAGE]], image will be rendered,
     * - 'imageLink' or [[self::PREVIEW_TYPE_IMAGE_LINK]], image as link to source file will be rendered,
     * - other string, which means the method 'renderPreview{OtherString}' will be called, result of which will be rendered.
     * 
     */
    public $previewType = 'link';

    /**
     * @var string the name of format link which must be rendered when file has been already uploaded.
     * Null (default) meaning link to the source file will be rendered.
     */
    public $previewFormat = null;

    /**
     * @var string the scheme for link which must be rendered when file has been already uploaded:
     *
     * - `false` (default): generating a relative URL.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::hostInfo]].
     * - string: generating an absolute URL with the specified scheme (either `http` or `https`).
     * 
     */
    public $previewScheme = false;

    /**
     * @var array of options that will be passed in `renderPreview*` method.
     */
    public $previewOptions = [];

    /**
     * @var string content that will be rendered if file with `previewFormat` will not exist.
     */
    public $emptyPreviewContent = '<em>In progress...</em>';

    /**
     * @var string template that used for rendering file input elements.
     * You can use {preview} and {input} placeholders there.
     */
    public $template = "<br/>\n{preview}<br/>\n{input}";

    /**
     * @var string template that used when file is empty (without preview).
     * Null meaning `$template` will be used with replacing {preview} as ''.
     * You can use {input} placeholder only there.
     */
    public $emptyFileTemplate = "<br/>\n{input}";

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
        $preview = $this->renderPreview();
        $input = $this->renderInput();
        $this->registerChangeEnctypeScripts($this->getInputId());

        if ($preview === null && $this->emptyFileTemplate !== null) {
            echo strtr($this->emptyFileTemplate, [
                '{input}' => $input,
            ]);
        } else {
            echo strtr($this->template, [
                '{preview}' => $preview,
                '{input}' => $input,
            ]);
        }
    }

    /**
     * Renders preview html according to [[self::$previewType]] property, if file has been already uploaded.
     * If file will not exist [[self::renderEmptyPreview()]] method will be called.
     * 
     * @return string|null string rendered preview or null. Null meaning file has not been uploaded yet.
     * @throws InvalidConfigException if incorrect preview type.
     */
    public function renderPreview()
    {
        if ($this->_file()->exists($this->previewFormat)) {
            $method = 'renderPreview' . ucfirst($this->previewType);
            if ($this->hasMethod($method)) {
                return $this->$method();
            }
            throw new InvalidConfigException("Invalid preview type '$this->previewType'. Method '$method' does not exist.");
        }
        if ($this->previewFormat !== null && $this->_file()->exists()) {
            return $this->renderEmptyPreview();
        }
        return null;
    }

    /**
     * Renders preview as span tag with file name as tag content.
     * @return string rendered span tag 
     */
    protected function renderPreviewSpan()
    {
        $tag = 'span';
        $options = $this->previewOptions;
        if (isset($options['tag'])) {
            $tag = $options['tag'];
            unset($options['tag']);
        }
        return Html::tag($tag, Html::encode($this->_file()->getName()), $this->previewOptions);
    }

    /**
     * Renders preview as link to the file.
     * [[self::$previewFormat]] and [[self::$previewScheme]] will be used for generating url.
     * [[self::$previewOptions]] will be used as tag 'a' html options. Attribute 'target' will be '_blank' by default.
     * @return string rendered link html tag.
     */
    protected function renderPreviewLink()
    {
        $name = $this->_file()->getName();
        $link = $this->_file()->getUrl($this->previewFormat, $this->previewScheme);
        $options = array_replace([
            'target' => '_blank',
            'title' => $name,
        ], $this->previewOptions);
        return Html::a(Html::encode($name), $link, $options);
    }

    /**
     * Renders preview as image.
     * [[self::$previewFormat]] and [[self::$previewScheme]] will be used for generating url.
     * [[self::$previewOptions]] will be used as tag 'img' html options.
     * @return string rendered img html tag.
     */
    protected function renderPreviewImage()
    {
        $link = $this->_file()->getUrl($this->previewFormat, $this->previewScheme);
        $name = $this->_file()->getName();

        $options = array_replace([
            'alt' => $name,
            'title' => $name,
        ], $this->previewOptions);

        return Html::img($link, $options);
    }

    /**
     * Renders preview as image, which will be a link to source file.
     * [[self::$previewFormat]] and [[self::$previewScheme]] will be used for generating url.
     * 
     * [[self::$previewOptions]] will be used as tag 'img' html options.
     * Special option 'linkOptions' will be used as html options of tag 'a'.
     * By default tag 'a' will be rendered with 'target' == '_blank' option.
     * 
     * @return string rendered img-link html tag.
     */
    protected function renderPreviewImageLink()
    {
        $name = $this->_file()->getName();
        $imgUrl = $this->_file()->getUrl($this->previewFormat, $this->previewScheme);
        $linkUrl = $this->_file()->getUrl(null, $this->previewScheme);

        $options = array_replace([
            'alt' => $name,
            'title' => $name,
        ], $this->previewOptions);

        $linkOptions = array_replace([
            'target' => '_blank',
            'title' => $name,
        ], isset($options['linkOptions']) ? $options['linkOptions'] : []);
        unset($options['linkOptions']);

        $imgContent = Html::img($imgUrl, $options);
        return Html::a($imgContent, $linkUrl, $linkOptions);
    }

    /**
     * Renders content that may be used if file with [[self::$previewFormat]] does not exists.
     * @return string rendered empty preview html content.
     */
    protected function renderEmptyPreview()
    {
        return $this->emptyPreviewContent;
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

        $accept = $this->_context()->inputAccept;
        if ($accept !== null && !isset($options['accept'])) {
            $options['accept'] = is_array($accept) ? implode(',', $accept) : $accept;
        }

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
