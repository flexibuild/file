<?php

namespace flexibuild\file\web;

use yii\web\Controller;

/**
 * UploadController can be used for process uploaded files.
 * It declares actions [[UploadAction]] for all declared contexts.
 * 
 * For each name the controller will declare action 'upload-{context-name}'
 * that can be used for uploading files in this context.
 * 
 * You can defaine any action params for each of contexts.
 * 
 * For example:
 * ```php
 *  'contexts' => [
 *      'default',
 *      'product' => [
 *          'param1' => 'value1',
 *          'param2' => 'value2',
 *      ],
 *  ],
 * ```
 *
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class UploadController extends Controller
{
    use UploadControllerTrait;
}
