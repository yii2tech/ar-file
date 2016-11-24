<?php

namespace yii2tech\tests\unit\ar\file\data;

use yii\db\ActiveRecord;
use yii2tech\ar\file\FileBehavior;

/**
 * @property int $id
 * @property string $name
 * @property string $fileExtension
 * @property string $fileVersion
 */
class File extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'file' => [
                'class' => FileBehavior::className(),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'File';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['name', 'required'],
        ];
    }
}