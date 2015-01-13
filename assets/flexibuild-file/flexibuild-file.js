flexibuild = typeof flexibuild === "undefined" ? {} : flexibuild;
flexibuild.file = (function ($) {
    var pub = {
        createArrayBufferFromUrl: function (url, callback, options) {
            typedResponseFromUrl(url, 'arraybuffer', callback, options);
        },
        createBlobFromUrl: function (url, callback, options) {
            typedResponseFromUrl(url, 'blob', callback, options);
        },

        createFileFromUrl: function (url, name, callback, options) {
            var lastModified;
            if ($.isPlainObject(options) && options.lastModified !== undefined) {
                lastModified = options.lastModified;
                if (options.hasOwnProperty('lastModified')) {
                    delete options['lastModified'];
                }
            } else {
                lastModified = new Date();
            }

            pub.createBlobFromUrl(url, function (blob) {
                var file = new File([blob], name, {
                    lastModified: lastModified,
                    type: blob.type
                });
                callback(file);
            }, options);
        },

        createFileListFromUrls: function (urls, callback, options) {
            var i, count, urlData, fileList, doneCount = 0;
            if (!$.isArray(urls)) {
                $.error('Parameter "url" must be an array.');
            }
            count = urls.length;
            fileList = new Array(count);

            if (count === 0) {
                callback(fileList);
                return;
            }

            for (i = 0, count = urls.length; i < count; ++i) {
                urlData = urls[i];
                if (urlData.url === undefined || urlData.name === undefined) {
                    $.error('Each url must have "url" and "name" properties.');
                }

                pub.createFileFromUrl(urlData.url, urlData.name, (function (index) {
                    return function (file) {
                        fileList[index] = file;
                        ++doneCount;
                        if (doneCount >= count) {
                            callback(fileList);
                        }
                    };
                })(i), options);
            }
        }
    };

    function typedResponseFromUrl(url, type, callback, options) {
        var xhr = new XMLHttpRequest(), i;
            options = options || {};

        xhr.open('GET', url, true);
        xhr.responseType = type;

        for (i in options) {
            if (options.hasOwnProperty(i)) {
                xhr[i] = options[i];
            }
        }

        xhr.onload = function(e) {
            if (this.status == 200) {
                callback(this.response);
            } else {
                $.error('Cannot load file by url: ' + url);
            }
        };
        xhr.send();
    }

    return pub;
})(jQuery);
