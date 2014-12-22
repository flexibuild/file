<?php

namespace flexibuild\file\contexts;

use flexibuild\file\validators\PdfValidator;

/**
 * Context for images. Main logic - use pdf validator as default.
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class PdfContext extends Context
{
    /**
     * @inheritdoc
     */
    public $inputAccept = 'application/pdf';

    /**
     * @inheritdoc
     */
    protected function defaultValidators()
    {
        return [PdfValidator::className()];
    }
}
