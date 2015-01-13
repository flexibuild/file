/**
 * This module keeps main JS logic of blueimp jQuery uploader widget.
 */
(function ($, tmpl, Math) {
    'use strict';
    var DATA_KEY = 'flexibuildBlueimpUploader';

    $.fn.flexibuildBlueimpUploader = function (method) {
        if (methods[method]) {
            return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof method === 'object' || !method) {
            return methods.init.apply(this, arguments);
        } else {
            return $(this).fileupload.apply(this, arguments);
        }
    };

    var methods = {
        init: function (options) {
            return this.each(function () {
                var $container = $(this);
                if ($container.data(DATA_KEY)) {
                    return;
                }

                var hiddenInputId = extractOption(options, 'hiddenInputId'),
                    fileInputId = extractOption(options, 'fileInputId'),
                    templateId = extractOption(options, 'templateId', null),
                    previewContainerId = extractOption(options, 'previewContainerId', null),
                    postParamPrefix = extractOption(options, 'postParamPrefix'),
                    progressBarId = extractOption(options, 'progressBarId', null),
                    errorContainer = extractOption(options, 'errorContainer', null),
                    removeContainerId = extractOption(options, 'removeContainerId', null),
                    fillFileList = extractOption(options, 'fillFileList', fillContainerFileList),

                __END_VAR__;

                options.dataType = options.dataType || 'json';
                if (options.url === undefined) {
                    $.error('Url param is required for ' + DATA_KEY + '.');
                }
                if (options.fileInput === undefined) {
                    options.fileInput = $container.find('#' + fileInputId);
                }
                if (options.dropZone === undefined) {
                    options.dropZone = $container;
                }
                if (options.pasteZone === undefined) {
                    options.pasteZone = $container;
                }
                if (options.formData === undefined) {
                    options.formData = createFormDataHandler(fileInputId, hiddenInputId);
                }

                $container.data(DATA_KEY, {
                    hiddenInputId: hiddenInputId,
                    fileInputId: fileInputId,
                    templateId: templateId,
                    previewContainerId: previewContainerId,
                    postParamPrefix: postParamPrefix,
                    progressBarId: progressBarId,
                    errorContainer: errorContainer,
                    removeContainerId: removeContainerId,
                    fillFileList: fillFileList,
                    settings: options,
                });

                fillFileList($container, []);

                $container.fileupload(options);
                $container.on('fileuploaddone.' + DATA_KEY, createOnDoneHandler($container));
                $container.on('fileuploadfail.' + DATA_KEY, createOnFailHandler($container));
                $container.on('fileuploadprogressall.' + DATA_KEY, createOnProgressAllHandler($container));
                if (removeContainerId !== null) {
                    $container.on('click.' + DATA_KEY, '#' + removeContainerId, createOnRemoveClickHandler($container));
                }
            });
        },

        destroy: function () {
            return this.each(function () {
                $(this).unbind('.' + DATA_KEY);
                $(this).removeData(DATA_KEY);
                $(this).fileupload('destroy');
            });
        },

        data: function () {
            return this.data(DATA_KEY);
        },

        populateFile: function (file) {
            return this.each(function () {
                var $container = $(this),
                    data = $container.data(DATA_KEY),
                    templateId = data.templateId,
                    previewContainerId = data.previewContainerId,
                    removeContainerId = data.removeContainerId,
                    fillFileList = data.fillFileList,
                    $previewContainer;

                if (previewContainerId) {
                    $previewContainer = $container.find('#' + previewContainerId);
                    if ($previewContainer.length && templateId && $('#' + templateId).length) {
                        $previewContainer.html(tmpl(templateId, file));
                    }
                }
                if (removeContainerId) {
                    $container.find('#' + removeContainerId).show();
                }

                fillFileList($container, [file], triggerFileChange);
            });
        },

        clearFile: function () {
            return this.each(function () {
                var $container = $(this),
                    data = $container.data(DATA_KEY),

                    previewContainerId = data.previewContainerId,
                    removeContainerId = data.removeContainerId,
                    hiddenInputId = data.hiddenInputId,
                    postParamPrefix = data.postParamPrefix,
                    fillFileList = data.fillFileList,

                    $hiddenInput = $container.find("#" + hiddenInputId),
                __END_VAR__;

                $hiddenInput.val(postParamPrefix);
                if (previewContainerId) {
                    $container.find("#" + previewContainerId).html('');
                }
                if (removeContainerId) {
                    $container.find("#" + removeContainerId).hide();
                }

                fillFileList($container, [], triggerFileChange);
            });
        }
    };

    function createFormDataHandler(fileInputId, hiddenInputId) {
        return function (form) {
            var $form = $(form);
            return $form.find("#" + hiddenInputId + ", #" + fileInputId).serializeArray();
        };
    }

    function createOnDoneHandler($container) {
        var data = $container.data(DATA_KEY),
            hiddenInputId = data.hiddenInputId,
            postParamPrefix = data.postParamPrefix,
            templateId = data.templateId,
            previewContainerId = data.previewContainerId,
            removeContainerId = data.removeContainerId,
            fillFileList = data.fillFileList,

            templateCompiled = false,
            $hiddenInput = $container.find('#' + hiddenInputId),
            $previewContainer = false,
            $removeContainer = false,
        __END_VAR__;

        if (previewContainerId) {
            $previewContainer = $container.find('#' + previewContainerId);
        }
        if ($previewContainer && $previewContainer.length && templateId && $('#' + templateId).length) {
            templateCompiled = tmpl(templateId);
        }
        if (removeContainerId) {
            $removeContainer = $container.find('#' + removeContainerId);
        }

        return function (e, data) {
            if (!data.result.success) {
                processError($container, data.result.message);
                return;
            }

            var file = data.result.file,
                value = file.value;
            $hiddenInput.val(postParamPrefix + value);
            if (templateCompiled) {
                $previewContainer.html(templateCompiled(file));
            }
            if ($removeContainer) {
                $removeContainer.show();
            }

            fillFileList($container, [file], triggerFileChange);
        };
    }

    function createOnFailHandler($container) {
        return function (e, data) {
            window.console && console.error && console.error("Upload error: ", data);
            processError($container, 'Internal server error');
        };
    }

    function createOnProgressAllHandler($container) {
        var data = $container.data(DATA_KEY),
            progressBarId = data.progressBarId,
            $progressBar;

        $progressBar = $container.find('#' + progressBarId);
        if (!$progressBar.length) {
            $progressBar = false;
        }

        return function (e, data) {
            if ($progressBar === false) {
                return;
            }
            var percent = data.total != 0 ? Math.round(data.loaded / data.total * 100) : 100;
            $progressBar.css('width', percent + '%');
        };
    }

    function createOnRemoveClickHandler($container) {
        return function () {
            try {
                methods.clearFile.call($container);
            } catch (err) {
                window.console && console.error && console.error(err);
            }
            return false;
        };
    }

    function processError($container, errorMessage) {
        var parseYiiActiveForm = function () {
            var $form,
                yiiActiveFormData,
                i, count,
                $attributeContainer,
                attribute = false,
                $errorContainer;

            $form = $container.closest('form');
            switch (false) {
                case $form.length: // no break
                case typeof $().yiiActiveForm !== "undefined": // no break
                case yiiActiveFormData = $form.yiiActiveForm('data'): // no break
                case $.isArray(yiiActiveFormData.attributes): // no break
                    return false;
            }

            for (i = 0, count = yiiActiveFormData.attributes.length; i < count; ++i) {
                $attributeContainer = $form.find(yiiActiveFormData.attributes[i].input);
                if ($attributeContainer.length !== 1 || $attributeContainer.get(0) !== $container.get(0)) {
                    continue;
                } else if (!attribute) {
                    attribute = yiiActiveFormData.attributes[i];
                } else {
                    return false; // more than one error attributes
                }
            }
            if (!attribute) {
                return false;
            }

            $attributeContainer = $form.find(attribute.container);
            $errorContainer = $attributeContainer.find(attribute.error);
            if (attribute.encodeError) {
                $errorContainer.text(errorMessage);
            } else {
                $errorContainer.html(errorMessage);
            }

            $attributeContainer
                .removeClass(yiiActiveFormData.settings.successCssClass)
                .addClass(yiiActiveFormData.settings.errorCssClass);

            return true;
        };

        var parseStringOrJQuery = function (errorContainer) {
            if (!(errorContainer instanceof $)) {
                errorContainer = $container.find(errorContainer);
            }
            errorContainer.text(errorMessage);
            return !!errorContainer.length;
        };

        var processErrorContainerProperty = function () {
            var data = $container.data(DATA_KEY),
                errorContainer = data.errorContainer,
            __END_VAR__;

            if (errorContainer === null) {
                return parseYiiActiveForm();
            } else if (errorContainer === !!errorContainer) { // is boolean
                return !errorContainer;
            } else {
                return parseStringOrJQuery(errorContainer);
            }
        };

        try {
            var result = processErrorContainerProperty();
        } catch (err) {
            window.console && console.error && console.error(err);
            result = false;
        }
        if (!result) {
            alert(errorMessage);
        }
    };

    function fillContainerFileList($container, files, afterFillCallback) {
        $container.get(0).files = files;
        if ($.isFunction(afterFillCallback)) {
            afterFillCallback.call($container);
        }
    }
    function triggerFileChange() {
        $(this).find('input:file').trigger('change');
    }

    function extractOption(options, name, defaultValue) {
        if ($.isPlainObject(options) && options[name] !== undefined) {
            var result = options[name];
            if (options.hasOwnProperty(name)) {
                delete options[name];
            }
            return result;
        } else if (defaultValue !== undefined) {
            return defaultValue;
        } else {
            $.error('Option ' + name + ' is required for blueimp jquery uploader.');
        }
    }
})(window.jQuery, window.tmpl, window.Math);
