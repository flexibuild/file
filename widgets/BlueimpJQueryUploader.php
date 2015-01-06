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
    //  @todo progress bar

    /**
     * Used only if [[self::$previewPath]] is null.
     * @var string type of preview, can be one of the followings:
     * 
     * - span - file name in span tag will be rendered,
     * - link - file name as link to source file will be rendered,
     * - image - image will be rendered,
     * - imageLink - image as link to source file will be rendered
     * 
     */
    public $previewType = self::PREVIEW_TYPE_LINK;

    /**
     * @var array array of options that will be passed to view when preview will be rendered.
     */
    public $previewData = [];

    /**
     * @var the path or Yii alias to preview view template.
     * If this property is set than [[self::$previewType]] param will be ignored.
     */
    public $previewPath;

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
            $this->clientOptions['formData'] = new JsExpression('function (form) {
                var $form = jQuery(form),
                    fileInputId = ' . Json::encode($this->fileInputOptions['id']) . ',
                    hiddenInputId = ' . Json::encode($this->hiddenInputOptions['id']) . ';
                return $form.find("#" + hiddenInputId + ", #" + fileInputId).serializeArray();
            }');
        }

        if (!isset($this->clientEvents['done'])) {
            $this->clientEvents['done'] = 'function (e, data) {
                if (!data.result.success) {
                    alert(data.result.message);
                    return;
                }

                var file = data.result.file,
                    value = file.value,

                    hiddenInputId = ' . Json::encode($this->hiddenInputOptions['id']) . ',
                    $hiddenInput = jQuery("#" + hiddenInputId),
                    prefix = ' . Json::encode($this->_context()->postParamStoragePrefix) . ',

                    templateId = ' . Json::encode($this->options['id'] . static::ID_PREVIEW_TEMPLATE_SUFFIX) . ',
                    containerId = ' . Json::encode($this->previewContainerOptions['id']) . ',
                    $container = jQuery("#" + containerId);

                $hiddenInput.val(prefix + value);
                if ($container.length && jQuery("#" + templateId).length) {
                    $container.html(window.tmpl(templateId, file));
                }
            }';
        }
        if (!isset($this->clientEvents['fail'])) {
            $this->clientEvents['fail'] = 'function (e, data) {
                alert("Internal server error");
                window.console && console.error && console.error("Upload error: ", data);
            }';
        }
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $preview = $this->renderPreview();
        $input = $this->renderInput();

        $this->registerWidget();
        return Html::tag($this->containerTag, strtr($this->template, [
            '{preview}' => $preview,
            '{input}' => $input,
        ]), $this->options);
    }

    /**
     * Renders and returns preview for file.
     * @return string rendered html content.
     */
    public function renderPreview()
    {
        $data = $this->previewData;

        if ($this->previewPath !== null) {
            $result = $this->render($this->previewPath, $data);
        } else {
            $path = '@flexibuild/file/widgets/views/blueimp-jquery-uploader';
            $result = $this->render("$path/$this->previewType", $data);
        }

        $template = Html::script($result, [
            'type' => 'text/x-tmpl',
            'id' => $this->options['id'] . static::ID_PREVIEW_TEMPLATE_SUFFIX,
        ]);
        return Html::tag($this->previewContainerTag, '', $this->previewContainerOptions) . $template;
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
            $this->getView()->registerJs("
                (function () {
                    var file = $fileData,
                        templateId = $previewTemplateId,
                        containerId = $previewContainerId,
                        \$container = jQuery('#' + containerId);
                    if (\$container.length && jQuery('#' + templateId).length) {
                        \$container.html(window.tmpl(templateId, file));
                    }
                })();
            ");
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
