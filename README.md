php-safe
========

Yii2 extension that allows to work with files as with simple properties.

Advantages
----------

1. Usage of files like any other simple property.

2. Storages logic is separated and can be change without changing your controllers or views.

3. Formatters logic is separated. For each of file you can define formatters.
This formatters allow to generate and use formatted versions of file.
Added useful watermakr, thumb, grayscale and others formatters for images.

4. Useful getting url of source file or formatted version of file.

5. File storage allows to save origin file names.

6. Useful config for default file url (for dummy urls).

7. You can use ajax file uploader with minimal application config.


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist "flexibuild/file *"
```

or add

```
"flexibuild/file": "*"
```

to the require section of your composer.json.


Usage
-----

Example of usage for avatar field in ContactForm model:

1. Add contextManager application component config in your cfg file:

```
    // ...
    'contextManager' => [
        'class' => 'flexibuild\file\ContextManager',
        'contexts' => [
            /* context name => [context config] */
            'contact' => [
                'class' => 'flexibuild\file\contexts\ImageContext',
                //'defaultFormatter' => ['image/watermark', 'filename' => '@app/data/menus.png'], // example of default watermark
                //'storage' => [
                //    'class' => 'flexibuild\file\storages\FileSystemStorage', // default storage
                //],
                //'formatters' => [ // examples of formatters
                //    'small' => ['image/thumb', 'width' => 200, 'height' => 200],
                //    'mini' => ['from', 'from' => 'small', 'formatter' => ['image/thumb', 'width' => 100]],
                //    'pixel' => ['from', 'from' => 'mini', 'formatter' => ['image/thumb', 'height' => 10]],
                //    'temp1' => ['image/thumb', 'width' => 100],
                //],
                //'fileConfig' => [ // example of customizing File objects
                //    'on cannotGetUrl' => 'flexibuild\file\events\CannotGetUrlHandlers::formatFileOnFly',
                //],
            ],
        ],
    // ...
```

2. Add flexibuild\file\ModelBehavior in your model. 
For example you have property $avatar in you model.

```
    // ...
    public function behaviors()
    {
        return [
            'fileModelBehavior' => [
                'class' => \flexibuild\file\ModelBehavior::className(),
                'attributes' => [
                    // add 'avatarFile' attribute that will be linked with 'avatar' property
                    // and use 'contact' context
                    'avatar' => 'contact',
                ],
            ],
        ];
    }
    // ...
```

After that your model has property 'avatarFile'. It type is \flexibuild\file\File object.
For better compability with IDE we recommend to add this property in your model's PHPDoc:

```
/*
 * ...
 * @property \flexibuild\file\File $avatarFile
 * ...
 */
```


3. For adding possibility to change 'avatarFile' through 'setAttributes()' method,
you must to add some rule or edit your scenarios() method. Example with 'rules()' method:

```
    public function rules()
    {
        return [
            // ...
            ['avatarFile', 'file'/*, 'skipOnEmpty' => false */],
            // ... 
        ];
    }
```

4. Add using one of the ```\flexibuild\file\widgets\SimpleFileInput or```
```\flexibuild\file\widgets\BlueimpJQueryUploader``` widgets in your view file.
You can use ActiveFormEx or ActiveFieldEx (or FieldFileInputsTrait) for greater convenience.

```
    <?php
        use flexibuild\file\widgets\bootstrap\ActiveFormEx;
        // or flexibuild\file\widgets\ActiveFormEx without using bootstrap
    ?>
    <!-- ... -->

    <?php $form = ActiveFormEx::begin([/* ... */]); ?>
    <!-- ... -->

    <?= $form->field($model, 'avatarFile')->fileInput([
        // custom widget options
    ]) ?>

    <!-- ... -->
    <?php ActiveFormEx::end(); ?>
```

5. For using AJAX file uploader you must to add upload action in your controller map.
For that you can use flexibuild\file\web\UploadAction in your controller or
use flexibuild\file\web\UploadController with contexts config:

```
    // ...
    'controllerMap' => [
        'file' => [
            'class' => 'flexibuild\file\web\UploadController',
            'contexts' => [
                'contact',
            ],
        ],
    ],
    // ...
```

After that instead of SimpleFileInput you can use ajax jquery uploader:

```
    <?= \flexibuild\file\widgets\BlueimpJQueryUploader::widget([
        'model' => $model,
        'attribute' => 'avatarFile',
        'url' => ['file/upload-contact'],
    ]) ?>
```

OR

```
    <?= $form->field($model, 'avatarFile')->fileBlueimpUploader([
        'url' => ['file/upload-contact'],
    ]) ?>
```

6. You can use your new file property not only in form. In other your views you can:

```
    <a href="<?= Html::encode($model->avatarFile) ?>"><?= Html::encode($model->name) ?></a>
    <a href="<?= Html::encode($model->avatarFile->asMini) ?>"><?= Html::encode($model->name) ?></a>
    <a href="<?= Html::encode($model->avatarFile->getUrl('small', 'https') ?>"><?= Html::encode($model->name) ?></a>
    <p>size: <?= $model->avatarFile->size ?></p>
```

See \flexibuild\file\File object for more info.

