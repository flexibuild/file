<?php

namespace flexibuild\file\widgets\assets;

use yii\web\AssetManager;

/**
 * AssetBundle extends yii asset bundle with [[self::$allowedDirectories]] param.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 */
class AssetBundle extends \yii\web\AssetBundle
{
    /**
     * @var array directories that allowed for copying.
     */
    public $allowedDirectories = [];

    /**
     * @var boolean whether the filter must to allow to copy file from root directory.
     * False by default.
     */
    public $copyRootDirFiles = false;

    /**
     * Publishes the asset bundle if its source code is not under Web-accessible directory.
     * It will also try to convert non-CSS or JS files (e.g. LESS, Sass) into the corresponding
     * CSS or JS files using [[AssetManager::converter|asset converter]].
     * 
     * This method set [[AssetManager::linkAssets]] value to false before publishing
     * and restore it after.
     * 
     * @param AssetManager $am the asset manager to perform the asset publishing
     */
    public function publish($am)
    {
        if (!count($this->allowedDirectories)) {
            return parent::publish($am);
        }

        $oldLinkAssets = $am->linkAssets;
        $am->linkAssets = false;

        try {
            $this->publishOptions['beforeCopy'] = \Yii::createObject(array(
                'class' => AssetDirectoriesFilter::className(),
                'assetManager' => $am,
                'assetBundle' => $this,
            ));
            $result = parent::publish($am);
        } catch (\Exception $ex) {
            $am->linkAssets = $oldLinkAssets;
            throw $ex;
        }

        $am->linkAssets = $oldLinkAssets;
        return $result;
    }
}
