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

This extension provides support for ActiveRecord file attachment.