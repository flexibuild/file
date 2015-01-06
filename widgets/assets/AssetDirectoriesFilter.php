<?php

namespace flexibuild\file\widgets\assets;

use Yii;
use yii\base\Object;
use yii\base\InvalidConfigException;
use yii\web\AssetManager;

/**
 * Class AssetDirectoriesFilter can be used in your assets for filtering directories
 * that must be copied.
 *
 * @author SeynovAM <sejnovalexey@gmail.com>
 * 
 * @property-read string $libDirectory the real path to asset bundle source path.
 */
class AssetDirectoriesFilter extends Object
{
    /**
     * @var AssetManager the asset manager to perform the asset publishing.
     */
    public $assetManager;

    /**
     * @var AssetBundle the asset bundle.
     */
    public $assetBundle;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (!isset($this->assetBundle)) {
            throw new InvalidConfigException('Param $assetBundle is required for ' . get_class($this) . '.');
        }
        if (!$this->assetBundle instanceof AssetBundle) {
            throw new InvalidConfigException('Param $assetBundle must be an instance of ' . AssetBundle::className() . '.');
        }
    }

    /**
     * Magic PHP method that will be called when object will be used as function.
     * This method is need for using this object like as \Closure.
     * @param string $from path to file or dir that will be copied.
     * @param string $to path to file or dir where $from will saved.
     * @return boolean whether need to copy the file|dir.
     */
    public function __invoke($from, $to)
    {
        $am = $this->assetManager;
        if ($am !== null && $am->beforeCopy !== null) {
            if (!call_user_func_array($am->beforeCopy, func_get_args())) {
                return false;
            }
        }
        if (strncmp(basename($from), '.', 1) === 0) {
            return false;
        }

        $libDirectory = $this->getLibDirectory();
        if (false === $parentDir = realpath(dirname($from))) {
            return false;
        }

        if ($libDirectory === $parentDir) {
            if (!is_dir($from)) {
                return $this->assetBundle->copyRootDirFiles;
            }
            return in_array(basename($from), $this->assetBundle->allowedDirectories, true);
        }

        return true;
    }

    /**
     * @var string keeps real path to asset bundle source path.
     */
    private $_libDirectory;

    /**
     * Returns the real path to asset bundle source path.
     * @return string the real path to asset bundle source path.
     * @throws InvalidConfigException if asset bundle has incorrect source path.
     */
    public function getLibDirectory()
    {
        if ($this->_libDirectory !== null) {
            return $this->_libDirectory;
        }

        $result = realpath(Yii::getAlias($this->assetBundle->sourcePath));
        if ($result === false) {
            throw new InvalidConfigException("Invalid asset bundle sourcePath '{$this->assetBundle->sourcePath}'.");
        }

        return $this->_libDirectory = $result;
    }
}
