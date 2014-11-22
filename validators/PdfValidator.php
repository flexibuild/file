<?php

namespace flexibuild\file\validators;

use yii\validators\FileValidator;

/**
 * Checks if file has pdf mime type and extension.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class PdfValidator extends FileValidator
{
    /**
     * @inheritdoc
     */
    public $extensions = ['pdf'];

    /**
     * @inheritdoc
     */
    public $mimeTypes = ['application/pdf'];
}
