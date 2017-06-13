<?php

namespace yii2tech\tests\unit\ar\file;

use yii2tech\ar\file\TransformFileBehavior;
use yii2tech\tests\unit\ar\file\data\TransformExtensionFile;
use yii2tech\tests\unit\ar\file\data\TransformFile;

class TransformFileBehaviorTest extends TestCase
{
    /**
     * Returns the test file path.
     * @return string test file full name.
     */
    protected function getTestFileFullName()
    {
        return __FILE__;
    }

    // Tests:

    public function testSetGet()
    {
        $behavior = new TransformFileBehavior();

        $defaultFileTransformName = 'test_default_file_transform_name';
        $behavior->setDefaultFileTransformName($defaultFileTransformName);
        $this->assertEquals($defaultFileTransformName, $behavior->getDefaultFileTransformName(), 'Unable to set default file transform name!');
    }

    /**
     * @depends testSetGet
     */
    public function testGetDefaultValueOfDefaultFileTransformName()
    {
        $behavior = new TransformFileBehavior();

        // Empty transform options:
        $behavior->setDefaultFileTransformName('');
        $expectedDefaultFileTransformName = 'default_file_transform_name_empty_transform_options';
        $behavior->fileTransformations = [$expectedDefaultFileTransformName];

        $returnedDefaultFileTransformName = $behavior->getDefaultFileTransformName();
        $this->assertEquals($expectedDefaultFileTransformName, $returnedDefaultFileTransformName, 'Unable to get default value of default file transform name from empty transform options!');

        // With transform options:
        $behavior->setDefaultFileTransformName('');
        $expectedDefaultFileTransformName = 'default_file_transform_name_has_transform_options';
        $behavior->fileTransformations = [$expectedDefaultFileTransformName => []];

        $returnedDefaultFileTransformName = $behavior->getDefaultFileTransformName();
        $this->assertEquals($expectedDefaultFileTransformName, $returnedDefaultFileTransformName, 'Unable to get default value of default file transform name with transform options!');
    }

    /**
     * @depends testSetGet
     */
    public function testSaveFile()
    {
        /* @var $model TransformFile|TransformFileBehavior */
        /* @var $refreshedModel TransformFile|TransformFileBehavior */

        $model = TransformFile::findOne(1);
        $fileTransformations = $model->fileTransformations;

        $testFileName = $this->getTestFileFullName();
        $testFileExtension = $this->getFileExtension($testFileName);

        $this->assertTrue($model->saveFile($testFileName), 'Unable to save file!');

        $refreshedModel = TransformFile::findOne($model->getPrimaryKey());
        $fileStorageBucket = $model->ensureFileStorageBucket();

        foreach ($fileTransformations as $transformationName => $transformation) {
            $returnedFileFullName = $model->getFileFullName($transformationName);

            $this->assertTrue($fileStorageBucket->fileExists($returnedFileFullName), "File for transformation name '{$transformationName}' does not exist!");
            $this->assertEquals($this->getFileExtension($returnedFileFullName), $testFileExtension, 'Saved file has wrong extension!');

            $this->assertEquals($refreshedModel->getFileFullName($transformationName), $returnedFileFullName, 'Wrong full file name from the refreshed record!');
        }
    }

    /**
     * @depends testSaveFile
     */
    public function testSaveFileWithEmptyTransforms()
    {
        /* @var $model TransformFile|TransformFileBehavior */

        $model = TransformFile::findOne(1);
        $model->fileTransformations = [];

        $testFileName = $this->getTestFileFullName();

        $this->expectException('yii\base\InvalidConfigException');
        $model->saveFile($testFileName);
    }

    /**
     * @depends testSetGet
     */
    public function testUseDefaultFileUrl()
    {
        /* @var $model TransformFile|TransformFileBehavior */

        $model = TransformFile::findOne(1);

        // Single string:
        $model->defaultFileUrl = [];
        $returnedFileWebSrc = $model->getFileUrl();
        $this->assertNotEmpty($returnedFileWebSrc, 'Unable to get file web src with empty default one!');
        $returnedFileWebSrc = $model->getFileUrl('custom');
        $this->assertNotEmpty($returnedFileWebSrc, 'Unable to get explicit transformation file web src with empty default one!');

        $testDefaultFileWebSrc = 'http://test/default/file/web/src';
        $model->defaultFileUrl = $testDefaultFileWebSrc;
        $returnedFileWebSrc = $model->getFileUrl();
        $this->assertEquals($returnedFileWebSrc, $testDefaultFileWebSrc, 'Default file web src does not used!');

        // Array:
        $transformNamePrefix = 'test_transform_';
        $defaultWebSrcPrefix = 'http://default/';
        $transformsCount = 3;
        $testDefaultFileWebSrcArray = [];
        for ($i = 1; $i <= $transformsCount; $i++) {
            $transformName = $transformNamePrefix . $i;
            $defaultWebSrc = $defaultWebSrcPrefix . $i . rand();
            $testDefaultFileWebSrcArray[$transformName] = $defaultWebSrc;
        }
        $model->defaultFileUrl = $testDefaultFileWebSrcArray;

        for ($i = 1; $i <= $transformsCount; $i++) {
            $transformName = $transformNamePrefix . $i;
            $returnedMainFileWebSrc = $model->getFileUrl($transformName);
            $this->assertEquals($returnedMainFileWebSrc, $testDefaultFileWebSrcArray[$transformName], 'Unable to apply default file web src per each transfromation!');
        }
    }

    /**
     * @depends testSaveFile
     * @depends testGetDefaultValueOfDefaultFileTransformName
     */
    public function testUseDefaultFileTransformName()
    {
        /* @var $model TransformFile|TransformFileBehavior */

        $model = TransformFile::findOne(1);

        $testFileName = $this->getTestFileFullName();
        $model->saveFile($testFileName);

        $defaultFileTransformName = $model->getDefaultFileTransformName();

        $this->assertEquals($model->getFileSelfName($defaultFileTransformName), $model->getFileSelfName(), 'Unable to get file self name for default file transform!');
        $this->assertEquals($model->getFileFullName($defaultFileTransformName), $model->getFileFullName(), 'Unable to get file full name for default file transform!');
        $this->assertEquals($model->getFileContent($defaultFileTransformName), $model->getFileContent(), 'Unable to get file content for default file transform!');
        $this->assertEquals($model->getFileUrl($defaultFileTransformName), $model->getFileUrl(), 'Unable to get file URL for default file transform!');
    }

    public function testTransformFileExtensions()
    {
        /* @var $model TransformExtensionFile|TransformFileBehavior */

        $model = TransformExtensionFile::findOne(1);
        $model->fileVersion = '1';
        $model->fileExtension = 'dat';

        $fileName = $model->getFileSelfName('origin');
        $this->assertEquals('.dat', substr($fileName, -4));

        $fileName = $model->getFileSelfName('text');
        $this->assertEquals('.txt', substr($fileName, -4));

        $model->transformationFileExtensions = [
            'callback' => function($extension) {
                return $extension . '_callback';
            },
        ];
        $fileName = $model->getFileSelfName('callback');
        $this->assertEquals('.dat_callback', substr($fileName, -13));

        $model->transformationFileExtensions = function($extension, $transformation) {
            return $transformation . '_' . $extension;
        };
        $fileName = $model->getFileSelfName('callback');
        $this->assertEquals('.callback_dat', substr($fileName, -13));
    }

    /**
     * @depends testSaveFile
     */
    public function testSaveFileWithTransformationExtensions()
    {
        /* @var $model TransformExtensionFile|TransformFileBehavior */
        /* @var $refreshedModel TransformExtensionFile|TransformFileBehavior */

        $model = TransformExtensionFile::findOne(1);

        $testFileName = $this->getTestFileFullName();
        $testFileExtension = $this->getFileExtension($testFileName);

        $this->assertTrue($model->saveFile($testFileName), 'Unable to save file!');

        $refreshedModel = TransformExtensionFile::findOne($model->getPrimaryKey());
        $fileStorageBucket = $model->ensureFileStorageBucket();

        foreach (['origin' => $testFileExtension, 'text' => 'txt'] as $transformationName => $extension) {
            $returnedFileFullName = $model->getFileFullName($transformationName);

            $this->assertTrue($fileStorageBucket->fileExists($returnedFileFullName), "File for transformation name '{$transformationName}' does not exist!");
            $this->assertEquals($this->getFileExtension($returnedFileFullName), $extension, 'Saved file has wrong extension!');

            $this->assertEquals($refreshedModel->getFileFullName($transformationName), $returnedFileFullName, 'Wrong full file name from the refreshed record!');
        }
    }

    /**
     * @depends testSaveFile
     */
    public function testRegenerateFileTransformations()
    {
        /* @var $model TransformFile|TransformFileBehavior */
        /* @var $refreshedModel TransformFile|TransformFileBehavior */

        $model = TransformFile::findOne(1);
        $model->fileTransformations = ['default'];

        $testFileName = $this->getTestFileFullName();

        $model->saveFile($testFileName);

        $refreshedModel = TransformFile::findOne($model->getPrimaryKey());

        $this->assertFalse($refreshedModel->fileExists('custom'));

        $this->assertTrue($refreshedModel->regenerateFileTransformations('default'));
        $this->assertTrue($refreshedModel->fileExists('custom'));
    }

    /**
     * @depends testSaveFile
     */
    public function testOpenFile()
    {
        /* @var $model TransformFile|TransformFileBehavior */
        /* @var $refreshedModel TransformFile|TransformFileBehavior */

        $model = TransformFile::findOne(1);
        $fileTransformations = $model->fileTransformations;

        $testFileName = $this->getTestFileFullName();

        $this->assertTrue($model->saveFile($testFileName), 'Unable to save file!');

        $refreshedModel = TransformFile::findOne($model->getPrimaryKey());

        foreach ($fileTransformations as $transformationName => $transformation) {
            $resource = $refreshedModel->openFile('r', $transformationName);
            $this->assertTrue(is_resource($resource));
            fclose($resource);
        }
    }
}