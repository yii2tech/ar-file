<?php

namespace yii2tech\tests\unit\ar\file\data;

use yii2tech\ar\file\TransformFileBehavior;

class TransformFile extends File
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'file' => [
                'class' => TransformFileBehavior::className(),
                'transformCallback' => 'copy',
                'fileTransformations' => [
                    'default' => null,
                    'custom' => null
                ],
                'defaultFileUrl' => [
                    'default' => 'http://test.default.url',
                    'custom' => 'http://test.custom.url'
                ],
            ],
        ];
    }
}