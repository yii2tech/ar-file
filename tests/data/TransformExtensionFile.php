<?php

namespace yii2tech\tests\unit\ar\file\data;

use yii2tech\ar\file\TransformFileBehavior;

class TransformExtensionFile extends File
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
                    'origin' => null,
                    'text' => null
                ],
                'defaultFileUrl' => [
                    'default' => 'http://test.default.url',
                    'custom' => 'http://test.custom.url'
                ],
                'transformationFileExtensions' => [
                    'text' => 'txt'
                ],
            ],
        ];
    }
}