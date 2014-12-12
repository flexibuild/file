<?php

namespace flexibuild\file;

use yii\web\UploadedFile;
use flexibuild\component\ComponentTrait;

/**
 * Class that implements events and behaviors logic for [[UploadedFile]].
 * 
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class FileComponent extends UploadedFile
{
    use ComponentTrait;
}
