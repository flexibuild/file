<?php

namespace flexibuild\file\widgets;

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;
use yii\jui\InputWidget;

use flexibuild\file\File;
use flexibuild\file\widgets\assets\BlueimpWidgetAsset;

/**
 * Widget that will be rendered as bluimp jQuery ajax file uploader.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class BlueimpJQueryUploader extends InputWidget
{
    /**
     * Const that will be used as suffix for id of hidden input by default.
     */
    const ID_HIDDEN_INPUT_SUFFIX = '-hidden';
    /**
     * Const that will be used as suffix for id of file input by default.
     */
    const ID_FILE_INPUT_SUFFIX = '-file';

    /**
     * Const that will be used as suffix for id of preview template script tag.
     */
    const ID_PREVIEW_TEMPLATE_SUFFIX = '-preview-template';
    /**
     * Const that will be used as suffix for id of preview container tag.
     */
    const ID_PREVIEW_CONTAINER_SUFFIX = '-preview-container';

    /**
     * Const that will be used as suffix for id of remove button container tag.
     */
    const ID_REMOVE_CONTAINER_SUFFIX = '-remove-container';

    /**
     * Const that will be used as suffix for id of progress bar container.
     */
    const ID_PROGRESS_SUFFIX = '-progress';


    /**
     * Predefined types for preview.
     */
    const PREVIEW_TYPE_SPAN = 'span';
    const PREVIEW_TYPE_LINK = 'link';
    const PREVIEW_TYPE_IMAGE = 'image';
    const PREVIEW_TYPE_IMAGE_LINK = 'imageLink';

    /**
     * @var string the url that will be used for uploading file.
     * You can use any aliases, because url will be passed through [[Url::to]].
     */
    public $url;

    /**
     * @var boolean|string the URI scheme to use in the URL for uploading files:
     *
     * - `false` (default): generating a relative URL.
     * - `true`: returning an absolute base URL whose scheme is the same as that in [[\yii\web\UrlManager::hostInfo]].
     * - string: generating an absolute URL with the specified scheme (either `http` or `https`).
     */
    public $scheme;

    /**
     * @var string the tag which will be used for the widget container.
     * You can change container options by changing [[self::$options]] property.
     */
    public $containerTag = 'div';

    /**
     * @var array of file input html options.
     */
    public $fileInputOptions = [];

    /**
     * @var array of hidden input html options.
     */
    public $hiddenInputOptions = [];

    /**
     * @var string template that will be used for rendering the widget.
     * You can use the followings placeholders:
     * 
     * - {input} - placeholder for hidden & file inputs,
     * - {progress} - placeholder for progress bar,
     * - {remove} - remove already uploaded file,
     * - {preview} - placeholder for preview
     * 
     */
    public $template = "{preview}\n{remove}\n{progress}\n{input}";

    /**
     * Used only if [[self::$previewViewPath]] is null.
     * @var string type of preview, can be one of the followings:
     * 
     * - span - file name in span tag will be rendered,
     * - link - file name as link to source file will be rendered,
     * - image - image will be rendered,
     * - imageLink - image as link to source file will be rendered
     * - string, which means the method 'renderPreview{OtherString}' will be called, result of which will be rendered,
     * 
     */
    public $previewType = self::PREVIEW_TYPE_LINK;

    /**
     * @var array array of options that will be passed to view when preview will be rendered.
     */
    public $previewData = [];

    /**
     * @var string the path or Yii alias to preview view template.
     * If this property is set than [[self::$previewType]] param will be ignored.
     * [[self::$previewData]] will be passed in the view as variables.
     */
    public $previewViewPath;

    /**
     * @var string the tag in which will be included preview content.
     */
    public $previewContainerTag = 'div';

    /**
     * @var arra array of html options of the tag in which will be included preview content.
     */
    public $previewContainerOptions = [];

    /**
     * @var callable the callback that will be used for converting File object to array.
     */
    public $convertCallback = 'flexibuild\file\web\UploadAction::convertFileToArray';

    /**
     * @var string the tag for progress bar container.
     */
    public $progressBarTag = 'div';

    /**
     * @var array html options of progress bar container.
     */
    public $progressBarOptions = [];

    /**
     * @var string the tag of remove button container. The tag 'a' by default.
     * If this property is null, remove button will not be rendered.
     */
    public $removeContainerTag = 'a';

    /**
     * @var array of remove button container html options. The 'href' = '#' (for tag 'a') by default.
     */
    public $removeContainerOptions = ['href' => '#'];

    /**
     * @var string the content of remove button container. This content will NOT be encoded.
     */
    public $removeContainerContent = 'x';

    /**
     * @var mixed container for upload errors, may be one of the followings:
     * 
     * - null (default), means the widget will try to auto detect this container.
     * It will to see if form has yii.activeForm jQuery plugin, than to use error value from it attribute.
     * If error container is not detected alert will be executed.
     * - true, means the widget has not error container, but alert will be executed.
     * - false, means the widget has not error container, alert will not be executed.
     * - string, jQuery selector of error container inside the widget container,
     * - [[\yii\web\JsExpression]], that returns jQuery object of container,
     * 
     */
    public $errorContainer = null;

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
        $file = Html::getAttributeValue($model, $this->attribute);
        if (!$file instanceof File) {
            throw new InvalidConfigException("Attribute $this->attribute of ".get_class($model).' must be an instance of '.File::className().' for usage in '.get_class($this).'.');
        }

        if (!isset($this->fileInputOptions['id'])) {
            $this->fileInputOptions['id'] = $this->options['id'] . static::ID_FILE_INPUT_SUFFIX;
        }
        if (!isset($this->hiddenInputOptions['id'])) {
            $this->hiddenInputOptions['id'] = $this->options['id'] . static::ID_HIDDEN_INPUT_SUFFIX;
        }
        if (!isset($this->previewContainerOptions['id'])) {
            $this->previewContainerOptions['id'] = $this->options['id'] . static::ID_PREVIEW_CONTAINER_SUFFIX;
        }

        $this->initClientOptions();
    }

    /**
     * Initializes client options.
     */
    protected function initClientOptions()
    {
        if (!isset($this->clientOptions['url'])) {
            $this->clientOptions['url'] = Url::to($this->url, $this->scheme);
        }

        if (!isset($this->clientOptions['fileInputId'])) {
            $this->clientOptions['fileInputId'] = $this->fileInputOptions['id'];
        }
        if (!isset($this->clientOptions['hiddenInputId'])) {
            $this->clientOptions['hiddenInputId'] = $this->hiddenInputOptions['id'];
        }
        if (!isset($this->clientOptions['previewContainerId'])) {
            $this->clientOptions['previewContainerId'] = $this->previewContainerOptions['id'];
        }
        if (!isset($this->clientOptions['templateId'])) {
            $this->clientOptions['templateId'] = $this->options['id'] . static::ID_PREVIEW_TEMPLATE_SUFFIX;
        }
        if (!isset($this->clientOptions['postParamPrefix'])) {
            $this->clientOptions['postParamPrefix'] = $this->_context()->postParamStoragePrefix;
        }
        if ($this->errorContainer !== null) {
            $this->clientOptions['errorContainer'] = $this->errorContainer;
        }
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $preview = $this->renderPreview();
        $input = $this->renderInput();
        $charset = \Yii::$app->charset;

        if (mb_strpos($this->template, '{progress}', 0, $charset) !== false) {
            $progress = $this->renderProgressBar();
        } else {
            $progress = '';
        }

        if ($this->removeContainerTag !== null && mb_strpos($this->template, '{remove}', 0, $charset) !== false) {
            $remove = $this->renderRemove();
        } else {
            $remove = '';
        }

        $this->registerAssets();
        return Html::tag($this->containerTag, strtr($this->template, [
            '{preview}' => $preview,
            '{input}' => $input,
            '{progress}' => $progress,
            '{remove}' => $remove,
        ]), $this->options);
    }

    /**
     * Renders and returns preview for file.
     * @return string rendered html content.
     */
    public function renderPreview()
    {
        $data = $this->previewData;

        if ($this->previewViewPath !== null) {
            $result = $this->render($this->previewViewPath, $data);
        } else {
            $method = 'renderPreview' . ucfirst($this->previewType);
            if (!$this->hasMethod($method)) {
                throw new \yii\base\InvalidConfigException("Incorrect preview type '$this->previewType'. Method $method does not exist.");
            }
            $result = $this->$method($data);
        }

        $template = Html::script($result, [
            'type' => 'text/x-tmpl',
            'id' => $this->options['id'] . static::ID_PREVIEW_TEMPLATE_SUFFIX,
        ]);
        return Html::tag($this->previewContainerTag, '', $this->previewContainerOptions) . $template;
    }

    /**
     * Renders preview as file name in span tag.
     * @param array $options array of span html options.
     * @return string rendered html content.
     */
    protected function renderPreviewSpan($options = [])
    {
        return Html::tag('span', '{%= o.name %}', $options);
    }

    /**
     * Renders preview as link to source file will be rendered,
     * @param array $options array of options. Optional options is:
     * 
     * - string 'format' - link to it format will be rendered,
     * - array 'htmlOptions' - array of link html options
     * 
     * @return string rendered html content.
     */
    protected function renderPreviewLink($options = [])
    {
        $htmlOptions = array_replace([
            'title' => '{%= o.name %}',
            'target' => '_blank',
        ], isset($options['htmlOptions']) ? $options['htmlOptions'] : []);

        $urlTemplate = isset($options['format'])
            ? '{%= o.formats.' . $options['format'] . ' %}'
            : '{%= o.url %}';

        return Html::a('{%= o.name %}', $urlTemplate, $htmlOptions);
    }

    /**
     * Renders preview as image,
     * @param array $options array of options. Optional options is:
     * 
     * - string 'format' - link to it format will be used as img src,
     * - array 'htmlOptions' - array of img html options
     * 
     * @return string rendered html content.
     */
    protected function renderPreviewImage($options = [])
    {
        $htmlOptions = array_replace([
            'title' => '{%= o.name %}',
            'alt' => '{%= o.name %}',
        ], isset($options['htmlOptions']) ? $options['htmlOptions'] : []);

        $urlTemplate = isset($options['format'])
            ? '{%= o.formats.' . $options['format'] . ' %}'
            : '{%= o.url %}';

        return Html::img($urlTemplate, $htmlOptions);
    }

    /**
     * Renders preview as image, image is link to source file.
     * @param array $options array of options. Optional options is:
     * 
     * - string 'format' - link to it format will be used as img src,
     * - array 'imgOptions' - array of img html options
     * - array 'linkOptions' - array of link html options
     * 
     * @return string rendered html content.
     */
    protected function renderPreviewImageLink($options = [])
    {
        $imgOptions = array_replace([
            'title' => '{%= o.name %}',
            'alt' => '{%= o.name %}',
        ], isset($options['imgOptions']) ? $options['imgOptions'] : []);

        $linkOptions = array_replace([
            'title' => '{%= o.name %}',
            'target' => '_blank',
            'href' => '{%= o.url %}',
        ], isset($options['linkOptions']) ? $options['linkOptions'] : []);

        $urlTemplate = isset($options['format'])
            ? '{%= o.formats.' . $options['format'] . ' %}'
            : '{%= o.url %}';

        $result = Html::beginTag('a', $linkOptions);
        $result .= Html::img($urlTemplate, $imgOptions);
        $result .= Html::endTag('a');

        return $result;
    }

    /**
     * Renders inputs for file. The method renders hidden and file inputs.
     * Hidden input is need for keeping current file storage data.
     * File input is need for possibillity to change existing file.
     * @return string the rendered inputs content.
     */
    public function renderInput()
    {
        $result = $this->renderHiddenInput();
        $result .= $this->renderFileInput();
        return $result;
    }

    /**
     * Returns html content for file input.
     */
    protected function renderFileInput()
    {
        $options = $this->fileInputOptions;
        if (!isset($options['name'])) {
            $options['name'] = 'file';
        }
        if (!isset($options['value'])) {
            $options['value'] = false;
        }

        $accept = $this->_context()->inputAccept;
        if ($accept !== null && !isset($options['accept'])) {
            $options['accept'] = is_array($accept) ? implode(',', $accept) : $accept;
        }

        return Html::activeInput('file', $this->model, $this->attribute, $options);
    }

    /**
     * Returns html content for hidden input.
     */
    protected function renderHiddenInput()
    {
        $options = $this->hiddenInputOptions;
        if (!isset($options['name'])) {
            $options['name'] = Html::getInputName($this->model, $this->attribute);
        }
        if (!isset($options['value'])) {
            $options['value'] = $this->_context()->postParamStoragePrefix . $this->_file()->getData();
        }
        return Html::activeHiddenInput($this->model, $this->attribute, $options);
    }

    /**
     * Renders progress bar and registers process JS handler.
     * @return string progress bar content.
     */
    public function renderProgressBar()
    {
        if (!isset($this->progressBarOptions['id'])) {
            $this->progressBarOptions['id'] = $this->options['id'] . static::ID_PROGRESS_SUFFIX;
        }
        if (!isset($this->clientOptions['progressBarId'])) {
            $this->clientOptions['progressBarId'] = $this->progressBarOptions['id'];
        }
        return Html::tag($this->progressBarTag, '', $this->progressBarOptions);
    }

    /**
     * Renders remove button container. Click on this button will remove already uploaded image.
     * @return string remove container html content.
     */
    public function renderRemove()
    {
        if (!isset($this->removeContainerOptions['id'])) {
            $this->removeContainerOptions['id'] = $this->options['id'] . static::ID_REMOVE_CONTAINER_SUFFIX;
        }
        if (!isset($this->clientOptions['removeContainerId'])) {
            $this->clientOptions['removeContainerId'] = $this->removeContainerOptions['id'];
        }

        $options = $this->removeContainerOptions;
        Html::addCssStyle($options, ['display' => 'none'], false);
        return Html::tag($this->removeContainerTag, $this->removeContainerContent, $options);
    }

    /**
     * Registers client scripts.
     */
    protected function registerAssets()
    {
        $name = 'flexibuildBlueimpUploader';
        $id = $this->options['id'];

        BlueimpWidgetAsset::register($this->getView());

        $this->registerClientOptions($name, $id);
        $this->registerClientEvents($name, $id);

        if ($this->_file()->exists()) {
            $fileData = Json::encode(call_user_func($this->convertCallback, $this->_file(), $this->scheme));
            $js = 'jQuery("#" + ' . Json::encode($id) . ").$name('populateFile', $fileData)";
            $this->getView()->registerJs($js);
        }
    }

    /**
     * Method-helper for retreiving File object.
     * @return File the File object.
     */
    private function _file()
    {
        return Html::getAttributeValue($this->model, $this->attribute);
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
