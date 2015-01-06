<?php
/* @var $this \yii\web\View */
use yii\helpers\Html;

$options = array_replace([
    'title' => '{%= o.name %}',
    'target' => '_blank',
], isset($options) ? $options : []);

$urlTemplate = isset($format)
    ? "{%= o.formats.$format %}"
    : '{%= o.url %}';
?>
<?= Html::a('{%= o.name %}', $urlTemplate, $options); ?>
