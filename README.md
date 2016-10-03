ActiveRecord File Attachment Extension for Yii2
===============================================

This extension provides support for ActiveRecord file attachment.

For license information check the [LICENSE](LICENSE.md)-file.

[![Latest Stable Version](https://poser.pugx.org/yii2tech/ar-file/v/stable.png)](https://packagist.org/packages/yii2tech/ar-file)
[![Total Downloads](https://poser.pugx.org/yii2tech/ar-file/downloads.png)](https://packagist.org/packages/yii2tech/ar-file)
[![Build Status](https://travis-ci.org/yii2tech/ar-file.svg?branch=master)](https://travis-ci.org/yii2tech/ar-file)


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist yii2tech/ar-file
```

or add

```json
"yii2tech/ar-file": "*"
```

to the require section of your composer.json.

If you wish to use [[yii2tech\ar\file\ImageFileBehavior]], you will also need to install [yiisoft/yii2-imagine](https://github.com/yiisoft/yii2-imagine),
which is not required by default. In order to do so either run

```
php composer.phar require --prefer-dist yiisoft/yii2-imagine
```

or add

```json
"yiisoft/yii2-imagine": "*"
```

to the require section of your composer.json.


Usage
-----

This extension provides support for ActiveRecord file attachment. Attached files are stored inside separated file storage,
which does not connected with ActiveRecord database.

This extension based on [yii2tech/file-storage](https://github.com/yii2tech/file-storage), and uses it as a
file saving layer. Thus attached files can be stored at any file storage such as local file system, Amazon S3 and so on.

First of all, you need to configure file storage, which will be used for attached files:

```php
return [
    'components' => [
        'fileStorage' => [
            'class' => 'yii2tech\filestorage\local\Storage',
            'basePath' => '@webroot/files',
            'baseUrl' => '@web/files',
            'filePermission' => 0777,
            'buckets' => [
                'item' => [
                    'baseSubPath' => 'item',
                ],
            ]
        ],
        // ...
    ],
    // ...
];
```

You should use [[\yii2tech\ar\file\FileBehavior]] behavior in order to allow your ActiveRecord file saving.
This can be done in following way:

```php
use yii2tech\ar\file\FileBehavior;

class Item extends \yii\db\ActiveRecord
{
    public function behaviors()
    {
        return [
            'file' => [
                'class' => FileBehavior::className(),
                'fileStorageBucket' => 'item',
                'fileExtensionAttribute' => 'fileExtension',
                'fileVersionAttribute' => 'fileVersion',
            ],
        ];
    }
    // ...
}
```

Usage of this behavior requires extra columns being present at the owner entity (database table):

 - [[\yii2tech\ar\file\FileBehavior::fileExtensionAttribute]] - used to store file extension, allowing to determine file type
 - [[\yii2tech\ar\file\FileBehavior::fileVersionAttribute]] - used to track file version, allowing browser cache busting

For example, DDL for the 'item' table may look like following:

```sql
CREATE TABLE `Item`
(
   `id` integer NOT NULL AUTO_INCREMENT,
   `name` varchar(64) NOT NULL,
   `description` text,
   `fileExtension` varchar(10),
   `fileVersion` integer,
    PRIMARY KEY (`id`)
) ENGINE InnoDB;
```

Once behavior is attached to may use `saveFile()` method on your ActiveRecord instance:

```php
$model = Item::findOne(1);
$model->saveFile('/path/to/source/file.dat');
```

This method will save source file inside file storage bucket, which has been specified inside behavior configuration,
and update file extension and version attributes.

You may delete existing file using `deleteFile()` method:

```php
$model = Item::findOne(1);
$model->deleteFile();
```

> Note: attached file will be automatically removed on owner deletion (`delete()` method invocation).

You may check existence of the file, get its content or URL:

```php
$model = Item::findOne(1);
if ($model->fileExists()) {
    echo $model->getFileUrl(); // outputs file URL
    echo $model->getFileContent(); // outputs file content
} else {
    echo 'No file attached';
}
```

> Tip: you may setup [[\yii2tech\ar\file\FileBehavior::defaultFileUrl]] in order to make `getFileUrl()`
  returning some default image URL in case actual attached file is missing.


## Working with web forms <span id="working-with-web-forms"></span>

Usually files for ActiveRecord are setup via web interface using file upload mechanism.
[[\yii2tech\ar\file\FileBehavior]] provides a special virtual property for the owner, which name is determined
by [[\yii2tech\ar\file\FileBehavior::fileAttribute]]. This property can be used to pass [[\yii\web\UploadedFile]]
instance or local file name, which should be attached to the ActiveRecord. This property is processed on
owner saving, and if set will trigger file saving. For example:

```php
use yii\web\UploadedFile;

$model = Item::findOne(1);
$model->file = UploadedFile::getInstance($model, 'file');
$model->save();

var_dump($model->fileExists()); // outputs `true`
```

> Attention: do NOT declare [[\yii2tech\ar\file\FileBehavior::fileAttribute]] attribute in the owner ActiveRecord class.
  Make sure it does not conflict with any existing owner field or virtual property.

If [[\yii2tech\ar\file\FileBehavior::autoFetchUploadedFile]] is enabled, behavior will attempt to fetch
uploaded file automatically before owner saving.

You may setup a validation rules for the file virtual attribute inside your model, specifying restrictions
for the attached file type, extension and so on:

```php
class Item extends \yii\db\ActiveRecord
{
    public function rules()
    {
        return [
            // ...
            ['file', 'file', 'mimeTypes' => ['image/jpeg', 'image/pjpeg', 'image/png', 'image/gif'], 'skipOnEmpty' => !$this->isNewRecord],
        ];
    }
    // ...
}
```

Inside view file you can use file virtual property for the form file input as it belongs to the owner model itself:

```php
<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $model Item */
?>
<?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

<?= $form->field($model, 'name'); ?>
<?= $form->field($model, 'description'); ?>

<?= $form->field($model, 'file')->fileInput(); ?>

<div class="form-group">
    <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
</div>

<?php ActiveForm::end(); ?>
```

Inside the controller you don't need any special code:

```php
use yii\web\Controller;

class ItemController extends Controller
{
    public function actionCreate()
    {
        $model = new Item();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view']);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    // ...
}
```


## File transformation <span id="file-transformation"></span>

Saving file "as it is" is not always enough for ActiveRecord attachment. Often files require some
processing, like image resizing, for example.

[[\yii2tech\ar\file\TransformFileBehavior]] is an enhanced version of the [[FileBehavior]] developed for
the managing files, which require some processing (transformations).
You should setup [[\yii2tech\ar\file\TransformFileBehavior::transformCallback]] to specify actual file processing
algorithm, and [[\yii2tech\ar\file\TransformFileBehavior::fileTransformations]] providing the list of named
processing and their specific settings. For example:

```php
use yii2tech\ar\file\TransformFileBehavior;
use yii\imagine\Image;

class Item extends \yii\db\ActiveRecord
{
    public function behaviors()
    {
        return [
            'file' => [
                'class' => TransformFileBehavior::className(),
                'fileStorageBucket' => 'item',
                'fileExtensionAttribute' => 'fileExtension',
                'fileVersionAttribute' => 'fileVersion',
                'transformCallback' => function ($sourceFileName, $destinationFileName, $options) {
                    try {
                        Image::thumbnail($sourceFileName, $options['width'], $options['height'])->save($destinationFileName);
                        return true;
                    } catch (\Exception $e) {
                        return false;
                    }
                },
                'fileTransformations' => [
                    'origin', // no transformation
                    'main' => [
                        'width' => 400,
                        'height' => 400,
                    ],
                    'thumbnail' => [
                        'width' => 100,
                        'height' => 100,
                    ],
                ],
            ],
        ];
    }
    // ...
}
```

In case of usage [[\yii2tech\ar\file\TransformFileBehavior]] methods `fileExists()`, `getFileContent()` and `getFileUrl()`
accepts first parameter as a transformation name, for which result should be returned:

```php
$model = Item::findOne(1);
echo $model->getFileUrl('origin'); // outputs URL for the full-sized image
echo $model->getFileUrl('main'); // outputs URL for the medium-sized image
echo $model->getFileUrl('thumbnail'); // outputs URL for the thumbnail image
```

Some file transformations may require changing the file extension. For example: you may want to create a preview for the
*.psd file in *.jpg format. You may specify file extension per each transformation using [[\yii2tech\ar\file\TransformFileBehavior::transformationFileExtensions]].
For example:

```php
use yii2tech\ar\file\TransformFileBehavior;
use yii\imagine\Image;

class Item extends \yii\db\ActiveRecord
{
    public function behaviors()
    {
        return [
            'file' => [
                'class' => TransformFileBehavior::className(),
                'fileTransformations' => [
                    'origin', // no transformation
                    'preview' => [
                        // ...
                    ],
                    'web' => [
                        // ...
                    ],
                ],
                'transformationFileExtensions' => [
                    'preview' => 'jpg',
                    'web' => function ($fileExtension) {
                        return in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif']) ? $fileExtension : 'jpg';
                    },
                ],
                // ...
            ],
        ];
    }
    // ...
}
```

You may face the issue, when settings for some file transformations change or new transformation added, as your project
evolves, making existing saved files outdated. In this case you can use [[\yii2tech\ar\file\TransformFileBehavior::regenerateFileTransformations()]]
method to regenerate transformation files with new settings using some existing transformation as source.
For example:

```php
$model = Item::findOne(1);
$model->regenerateFileTransformations('origin'); // regenerate transformations using 'origin' as a source
```


## Image file transformation <span id="image-file-transformation"></span>

The most common file transformation use case is an image resizing. Thus a special behavior
[[\yii2tech\ar\file\ImageFileBehavior]] is provided. This behavior provides image resize transformation
via [yiisoft/yii2-imagine](https://github.com/yiisoft/yii2-imagine) extension.
Configuration example:

```php
use yii2tech\ar\file\ImageFileBehavior;

class Item extends \yii\db\ActiveRecord
{
    public function behaviors()
    {
        return [
            'file' => [
                'class' => ImageFileBehavior::className(),
                'fileStorageBucket' => 'item',
                'fileExtensionAttribute' => 'fileExtension',
                'fileVersionAttribute' => 'fileVersion',
                'fileTransformations' => [
                    'origin', // no resize
                    'main' => [800, 600], // width = 800px, height = 600px
                    'thumbnail' => [100, 80], // width = 100px, height = 80px
                ],
            ],
        ];
    }
    // ...
}
```

> Note: this package does not include "yiisoft/yii2-imagine", you should install it yourself.
