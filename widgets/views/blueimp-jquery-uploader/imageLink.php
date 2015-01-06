<?php
/* @var $this \yii\web\View */
use yii\helpers\Html;

$imgOptions = array_replace([
    'title' => '{%= o.name %}',
    'alt' => '{%= o.name %}',
], isset($imgOptions) ? $imgOptions : []);

$linkOptions = array_replace([
    'title' => '{%= o.name %}',
    'target' => '_blank',
    'href' => '{%= o.url %}',
], isset($linkOptions) ? $linkOptions : []);

$urlTemplate = isset($format)
    ? "{%= o.formats.$format %}"
    : '{%= o.url %}';
?>
<?= Html::beginTag('a', $linkOptions) ?>
    <?= Html::img($urlTemplate, $imgOptions); ?>
<?= Html::endTag('a'); ?>
