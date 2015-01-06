<?php
/* @var $this \yii\web\View */
use yii\helpers\Html;

$options = array_replace([
    'title' => '{%= o.name %}',
    'alt' => '{%= o.name %}',
], isset($options) ? $options : []);

$urlTemplate = isset($format)
    ? "{%= o.formats.$format %}"
    : '{%= o.url %}';
?>
<?= Html::img($urlTemplate, $options); ?>
