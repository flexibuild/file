<?php

namespace flexibuild\file\web;

use yii\web\Controller;

/**
 * UploadController can be used for process uploaded files.
 * It declares actions [[UploadAction]] for all declared contexts.
 * 
 * @todo examples here and in the trait
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class UploadController extends Controller
{
    use UploadControllerTrait;
}
