<?php

namespace yii2tech\tests\unit\ar\file\data;

use yii2tech\ar\file\ImageFileBehavior;

class ImageFile extends File
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'file' => [
                'class' => ImageFileBehavior::className(),
                'fileTransformations' => [
                    'full' => [800, 600],
                    'thumbnail' => [200, 150]
                ],
            ],
        ];
    }
}