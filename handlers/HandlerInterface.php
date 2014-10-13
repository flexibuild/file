<?php

namespace flexibuild\file\handlers;

/**
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
interface HandlerInterface
{
    
    /**
     * Gets file handler validators.
     * @return \yii\validators\Validator[]
     */
    public function getValidators();
}
