<?php

namespace flexibuild\file\widgets;

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;
use yii\web\JsExpression;
use yii\jui\InputWidget;

use flexibuild\file\File;
use flexibuild\file\widgets\assets\BlueimpFileUploadAsset;
use flexibuild\file\widgets\assets\BlueimpTmplAsset;

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
     * - {preview} - placeholder for preview
     * 
     */
    public $template = "{preview}\n{progress}\n{input}";

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

        if (!isset($this->fileInputOptions['id'])) {
            $this->fileInputOptions['id'] = $this->options['id'] . static::ID_FILE_INPUT_SUFFIX;
        }
        if (!isset($this->hiddenInputOptions['id'])) {
            $this->hiddenInputOptions['id'] = $this->options['id'] . static::ID_HIDDEN_INPUT_SUFFIX;
        }
        if (!isset($this->previewContainerOptions['id'])) {
            $this->previewContainerOptions['id'] = $this->options['id'] . static::ID_PREVIEW_CONTAINER_SUFFIX;
        }
        if (!isset($this->progressBarOptions['id'])) {
            $this->progressBarOptions['id'] = $this->options['id'] . static::ID_PROGRESS_SUFFIX;
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
        if (!isset($this->clientOptions['dataType'])) {
            $this->clientOptions['dataType'] = 'json';
        }

        if (!isset($this->clientOptions['fileInput'])) {
            $this->clientOptions['fileInput'] = new JsExpression('jQuery("#" + ' . Json::encode($this->fileInputOptions['id']) . ')');
        }
        if (!isset($this->clientOptions['dropZone'])) {
            $this->clientOptions['dropZone'] = new JsExpression('jQuery("#" + ' . Json::encode($this->options['id']) . ')');
        }
        if (!isset($this->clientOptions['pasteZone'])) {
            $this->clientOptions['pasteZone'] = new JsExpression('jQuery("#" + ' . Json::encode($this->options['id']) . ')');;
        }

        if (!isset($this->clientOptions['formData'])) {
            $this->clientOptions['formData'] = new JsExpression($this->jsFormDataFunction());
        }

        if (!isset($this->clientEvents['done'])) {
            $this->clientEvents['done'] = $this->jsOnDoneFunction();
        }
        if (!isset($this->clientEvents['fail'])) {
            $this->clientEvents['fail'] = $this->jsOnFailFunction();
        }
    }

    /**
     * Returns JS function for 'formData' uploader option.
     * @return string function body for 'formData' option.
     */
    protected function jsFormDataFunction()
    {
        return 'function (form) {
            var $form = jQuery(form),
                fileInputId = ' . Json::encode($this->fileInputOptions['id']) . ',
                hiddenInputId = ' . Json::encode($this->hiddenInputOptions['id']) . ';
            return $form.find("#" + hiddenInputId + ", #" + fileInputId).serializeArray();
        }';
    }

    /**
     * Returns JS function - handler on 'done' uploader event.
     * @return string function body for using as handler on 'done' uploader event.
     */
    protected function jsOnDoneFunction()
    {
        return '(function () {
            var $hiddenInput = jQuery("#" + ' . Json::encode($this->hiddenInputOptions['id']) . '),
                prefix = ' . Json::encode($this->_context()->postParamStoragePrefix) . ',
                templateId = ' . Json::encode($this->options['id'] . static::ID_PREVIEW_TEMPLATE_SUFFIX) . ',
                templateCompiled = false,
                $container = jQuery("#" + ' . Json::encode($this->previewContainerOptions['id']) . ');

            if (jQuery("#" + templateId).length) {
                templateCompiled = window.tmpl(templateId);
            }

            return function (e, data) {
                if (!data.result.success) {
                    alert(data.result.message);
                    return;
                }

                var file = data.result.file,
                    value = file.value;
                $hiddenInput.val(prefix + value);
                if ($container.length && templateCompiled) {
                    $container.html(templateCompiled(file));
                }
            };
        })()';
    }

    /**
     * Returns JS function - handler on 'fail' uploader event.
     * @return string function body for using as handler on 'fail' uploader event.
     */
    protected function jsOnFailFunction()
    {
        return 'function (e, data) {
            alert("Internal server error");
            window.console && console.error && console.error("Upload error: ", data);
        }';
    }

    /**
     * Returns JS function - handler on 'progressall' uploader event.
     * @return string function body for using as handler on 'progressall' uploader event.
     */
    protected function jsOnProgressAllFunction()
    {
        $id = Json::encode($this->progressBarOptions['id']);
        return "(function () {
            var \$progressBar = jQuery('#' + $id);
            return function (e, data) {
                var percent = Math.round(data.loaded / data.total * 100);
                \$progressBar.css('width', percent + '%');
            };
        })()";
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $preview = $this->renderPreview();
        $input = $this->renderInput();

        if (mb_strpos($this->template, '{progress}', 0, \Yii::$app->charset) !== false) {
            $progress = $this->renderProgressBar();
        } else {
            $progress = '';
        }

        $this->registerWidget();
        return Html::tag($this->containerTag, strtr($this->template, [
            '{preview}' => $preview,
            '{input}' => $input,
            '{progress}' => $progress,
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
        $result = Html::tag($this->progressBarTag, '', $this->progressBarOptions);

        if (!isset($this->clientEvents['progressall'])) {
            $this->clientEvents['progressall'] = $this->jsOnProgressAllFunction();
        }

        return $result;
    }

    /**
     * Registers client scripts.
     * @inheritdoc
     */
    protected function registerWidget($name = 'fileupload', $id = null)
    {
        if ($id === null) {
            $id = $this->options['id'];
        }

        BlueimpFileUploadAsset::register($this->getView());
        BlueimpTmplAsset::register($this->getView());

        $this->registerClientOptions($name, $id);
        $this->registerClientEvents($name, $id);

        if ($this->_file()->exists()) {
            $fileData = Json::encode(call_user_func($this->convertCallback, $this->_file(), $this->scheme));
            $previewTemplateId = Json::encode($this->options['id'] . static::ID_PREVIEW_TEMPLATE_SUFFIX);
            $previewContainerId = Json::encode($this->previewContainerOptions['id']);
            $this->getView()->registerJs("(function () {
                var file = $fileData,
                    templateId = $previewTemplateId,
                    containerId = $previewContainerId,
                    \$container = jQuery('#' + containerId);
                if (\$container.length && jQuery('#' + templateId).length) {
                    \$container.html(window.tmpl(templateId, file));
                }
            })();");
        }
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
