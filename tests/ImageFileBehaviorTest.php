<?php

namespace yii2tech\tests\unit\ar\file;

use Yii;
use yii2tech\ar\file\ImageFileBehavior;
use yii2tech\tests\unit\ar\file\data\ImageFile;

class ImageFileBehaviorTest extends TestCase
{
    protected function setUp()
    {
        if (!class_exists('yii\imagine\Image')) {
            $this->markTestSkipped('"yiisoft/yii2-imagine" extension required');
            return;
        }
        parent::setUp();
    }

    /**
     * Returns the test image file path.
     * @return string test image file full name.
     */
    protected function getTestImageFileFullName()
    {
        return Yii::getAlias('@yii2tech/tests/unit/ar/file/data/files/image.jpg');
    }

    // Tests:

    public function testSaveFile()
    {
        /* @var $model ImageFile|ImageFileBehavior */
        /* @var $refreshedModel ImageFile|ImageFileBehavior */

        $model = ImageFile::findOne(1);

        $imageTransforms = $model->fileTransforms;

        $testFileName = $this->getTestImageFileFullName();
        $testFileExtension = $this->getFileExtension($testFileName);

        $this->assertTrue($model->saveFile($testFileName), 'Unable to save file!');

        $refreshedModel = ImageFile::findOne($model->getPrimaryKey());

        foreach ($imageTransforms as $imageTransformName => $imageTransform) {
            $returnedFileFullName = $model->getFileFullName($imageTransformName);
            $fileStorageBucket = $model->getFileStorageBucket();

            $this->assertTrue($fileStorageBucket->fileExists($returnedFileFullName), "File for transformation name '{$imageTransformName}' does not exist!");
            $this->assertEquals($this->getFileExtension($returnedFileFullName), $testFileExtension, 'Saved file has wrong extension!');

            $this->assertEquals($refreshedModel->getFileFullName($imageTransformName), $returnedFileFullName, 'Wrong full file name from the refreshed record!');
        }
    }
}
