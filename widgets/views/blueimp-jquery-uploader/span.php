<?php
/* @var $this \yii\web\View */
use yii\helpers\Html;

$options = isset($options) ? $options : [];
?>
<?= Html::tag('span', '{%= o.name %}', $options) ?>
