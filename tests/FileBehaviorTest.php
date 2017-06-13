<?php

namespace yii2tech\tests\unit\ar\file;

use Yii;
use yii2tech\ar\file\FileBehavior;
use yii2tech\tests\unit\ar\file\data\File;

class FileBehaviorTest extends TestCase
{
    public function testGetFileStorageBucket()
    {
        /* @var $model File|FileBehavior */

        $testBucketName = 'testBucketName';
        Yii::$app->fileStorage->addBucket($testBucketName);

        $model = new File();
        $model->fileStorageBucket = $testBucketName;

        $fileStorageBucket = $model->ensureFileStorageBucket();

        $this->assertTrue(is_object($fileStorageBucket), 'Unable to get file storage bucket!');
        $this->assertEquals($testBucketName, $fileStorageBucket->getName(), 'Returned file storage bucket has incorrect name!');
    }

    /**
     * @depends testGetFileStorageBucket
     */
    public function testGetDefaultFileStorageBucketName()
    {
        /* @var $model File|FileBehavior */
        $model = new File();

        $model->fileStorageBucket = null;

        $fileStorageBucket = $model->ensureFileStorageBucket();

        $this->assertNotEmpty($fileStorageBucket->getName(), 'Unable to get default file storage bucket name!');
    }

    /**
     * @depends testGetFileStorageBucket
     */
    public function testGetFileStorageBucketIfNotExists()
    {
        /* @var $model File|FileBehavior */

        Yii::$app->fileStorage->setBuckets([]);

        $testBucketName = 'testBucketNameWhichNotPresentInStorage';

        $model = new File();
        $model->fileStorageBucket = $testBucketName;

        $fileStorageBucket = $model->ensureFileStorageBucket();

        $this->assertTrue(is_object($fileStorageBucket), 'Unable to get file storage bucket!');
        $this->assertEquals($testBucketName, $fileStorageBucket->getName(), 'Returned file storage bucket has incorrect name!');
    }

    public function testGetActualSubDirPath()
    {
        /* @var $model File|FileBehavior */

        $model = File::findOne(1);

        $model->subDirTemplate = null;
        $actualSubDir = $model->getActualSubDir();
        $this->assertEquals('', $actualSubDir, 'Actual sub dir can not parse primary key!');

        $testSubDirTemplate = 'test/{pk}/subdir/template';
        $model->subDirTemplate = $testSubDirTemplate;
        $actualSubDir = $model->getActualSubDir();
        $expectedActualSubDir = str_replace('{pk}', $model->getPrimaryKey(), $testSubDirTemplate);
        $this->assertEquals($expectedActualSubDir, $actualSubDir, 'Actual sub dir can not parse primary key!');

        $model->id = 54321;
        $testSubDirTemplate = 'test/{^pk}/subdir/template';
        $model->subDirTemplate = $testSubDirTemplate;
        $actualSubDir = $model->getActualSubDir();
        $expectedActualSubDir = str_replace('{^pk}', substr($model->getPrimaryKey(),0,1), $testSubDirTemplate);
        $this->assertEquals($expectedActualSubDir, $actualSubDir, 'Actual sub dir can not parse primary key first symbol!');

        $model->id = 54321;
        $testSubDirTemplate = 'test/{^^pk}/subdir/template';
        $model->subDirTemplate = $testSubDirTemplate;
        $actualSubDir = $model->getActualSubDir();
        $expectedActualSubDir = str_replace('{^^pk}', substr($model->getPrimaryKey(),1,1), $testSubDirTemplate);
        $this->assertEquals($expectedActualSubDir, $actualSubDir, 'Actual sub dir can not parse primary key second symbol!');

        $testPropertyName = 'name';
        $testSubDirTemplate = 'test/{' . $testPropertyName . '}/subdir/template';
        $model->subDirTemplate = $testSubDirTemplate;
        $actualSubDir = $model->getActualSubDir();
        $expectedActualSubDir = str_replace('{' . $testPropertyName . '}', $model->$testPropertyName, $testSubDirTemplate);
        $this->assertEquals($expectedActualSubDir, $actualSubDir, 'Actual sub dir can not parse property!');

        $model->id = 17;
        $model->subDirTemplate = function ($model) {
            return 'test/closure/' . $model->id;
        };
        $actualSubDir = $model->getActualSubDir();
        $this->assertEquals('test/closure/17', $actualSubDir, 'Actual sub dir can not parse callable!');

        $model->subDirTemplate = '{__model__}';
        $actualSubDir = $model->getActualSubDir();
        $expectedActualSubDir = str_replace('\\', '_', File::className());
        $this->assertEquals($expectedActualSubDir, $actualSubDir, '{__model__} placeholder parsed incorrectly!');

        $model->subDirTemplate = '{__basemodel__}';
        $actualSubDir = $model->getActualSubDir();
        $this->assertEquals('File', $actualSubDir, '{__basemodel__} placeholder parsed incorrectly!');

        $model->subDirTemplate = '{__modelid__}';
        $actualSubDir = $model->getActualSubDir();
        $this->assertEquals('file', $actualSubDir, '{__modelid__} placeholder parsed incorrectly!');
    }

    /**
     * @depends testGetActualSubDirPath
     */
    public function testSaveFile()
    {
        /* @var $model File|FileBehavior */
        /* @var $refreshedModel File|FileBehavior */

        $model = File::findOne(1);

        $testFileExtension = 'ext';
        $testFileSelfName = 'test_file_name.'.$testFileExtension;
        $testFileContent = 'Test File Content';
        $testFileName = $this->saveTestFile($testFileSelfName, $testFileContent);

        $this->assertTrue($model->saveFile($testFileName), 'Unable to save file!');

        $returnedFileFullName = $model->getFileFullName();
        $fileStorageBucket = $model->ensureFileStorageBucket();

        $this->assertTrue($fileStorageBucket->fileExists($returnedFileFullName), 'Unable to save file in the file storage bucket!');

        $this->assertEquals($fileStorageBucket->getFileContent($returnedFileFullName), $testFileContent, 'Saved file has wrong content!');
        $this->assertEquals($this->getFileExtension($returnedFileFullName), $testFileExtension, 'Saved file has wrong extension!');

        $refreshedModel = File::findOne($model->getPrimaryKey());
        $this->assertEquals($refreshedModel->getFileFullName(), $returnedFileFullName, 'Wrong file full name returned from the refreshed record!');
    }

    public function testGetUploadedFileFromRequest()
    {
        /* @var $model File|FileBehavior */

        $model = new File();

        $this->mockUpUploadedFile($model, 'test_file_name.txt', 'Test File Content');

        $uploadedFile = $model->getUploadedFile();
        $this->assertTrue(is_object($uploadedFile), 'Unable to get uploaded file from request!');
    }

    /**
     * @depends testGetUploadedFileFromRequest
     */
    public function testGetUploadedFileFromRequestDisabledAutoFetch()
    {
        /* @var $model File|FileBehavior */

        $model = new File();
        $model->autoFetchUploadedFile = false;

        $this->mockUpUploadedFile($model, 'test_file_name.txt', 'Test File Content');

        $uploadedFile = $model->getUploadedFile();
        $this->assertFalse(is_object($uploadedFile), 'File found while auto fetch uploaded file is disabled!');
    }

    /**
     * @depends testGetUploadedFileFromRequest
     */
    public function testGetUploadedFileFromRequestTwice()
    {
        /* @var $model File|FileBehavior */

        $model = new File();

        $testFileName = 'test_file_name.txt';
        $this->mockUpUploadedFile($model, $testFileName, 'Test File Content');

        $firstUploadedFile = $model->getUploadedFile();
        $model->setUploadedFile(null);

        $this->deleteTestFile($testFileName);

        $secondUploadedFile = $model->getUploadedFile();
        $this->assertFalse(is_object($secondUploadedFile), 'Same uploaded file is fetched from request twice!');
    }

    /**
     * @depends testSaveFile
     */
    public function testFilePropertySetUp()
    {
        /* @var $model File|FileBehavior */

        $modelModel = new File();

        $testFileSelfName = 'test_file_name.test';
        $testFileContent = 'Test File Content';
        $testFileName = $this->saveTestFile($testFileSelfName, $testFileContent);

        $modelModel->file = $testFileName;

        $this->assertTrue(is_object($modelModel->file), 'Unable to set file property!');
        $this->assertEquals($modelModel->file->tempName, $testFileName, 'Wrong temp file name, while setting file property!');
    }

    /**
     * @depends testSaveFile
     */
    public function testModelSave()
    {
        /* @var $model File|FileBehavior */

        $model = File::findOne(1);

        $testFileSelfName = 'test_file_name.test';
        $testFileContent = 'Test File Content';
        $testFileName = $this->saveTestFile($testFileSelfName, $testFileContent);

        $model->file = $testFileName;

        $this->assertTrue($model->save(), 'Unable to save record with file!');

        $fileStorageBucket = $model->ensureFileStorageBucket();
        $returnedFileFullName = $model->getFileFullName();

        $this->assertTrue($fileStorageBucket->fileExists($returnedFileFullName), 'Unable to save file in the file storage bucket!');
        $this->assertEquals($fileStorageBucket->getFileContent($returnedFileFullName), $testFileContent, 'Saved file has wrong content!');
    }

    /**
     * @depends testSaveFile
     */
    public function testSaveFileFromWeb()
    {
        /* @var $model File|FileBehavior */

        $model = new File();
        $model->name = 'test_name';

        $testFileContent = 'Test File Content';
        $this->mockUpUploadedFile($model, 'test_file_name.txt', $testFileContent);

        $this->assertTrue($model->save(), 'Unable to save record with file fetched from Web!');

        $fileStorageBucket = $model->ensureFileStorageBucket();
        $returnedFileFullName = $model->getFileFullName();

        $this->assertTrue($fileStorageBucket->fileExists($returnedFileFullName), 'Unable to save file from Web in the file storage bucket!');
        $this->assertEquals($fileStorageBucket->getFileContent($returnedFileFullName), $testFileContent, 'Saved from Web file has wrong content!');
    }

    /**
     * @depends testSaveFileFromWeb
     */
    public function testSaveFromWebTabular()
    {
        /* @var $models File[]|FileBehavior[] */

        $models = [];

        $testActiveRecordCount = 10;

        for ($i = 1; $i <= $testActiveRecordCount; $i++) {
            $model = new File();
            $model->name = 'test_name_' . $i;
            $models[] = $model;
        }

        // Mock up $_FILES:
        $testFileContents = [];
        foreach ($models as $index => $model) {
            $testFileContent = "Test File Content {$index}";
            $testFileContents[] = $testFileContent;
            $this->mockUpUploadedFile($model, "test_file_name_{$index}.test", $testFileContent, $index);
        }

        foreach ($models as $index => $model) {
            $model->fileTabularInputIndex = $index;

            $this->assertTrue($model->save(), 'Unable to save record with tabular input file!');

            $fileStorageBucket = $model->ensureFileStorageBucket();
            $returnedFileFullName = $model->getFileFullName();

            $this->assertTrue($fileStorageBucket->fileExists($returnedFileFullName), 'Unable to save file with tabular input!');
            $this->assertEquals($fileStorageBucket->getFileContent($returnedFileFullName), $testFileContents[$index], 'Saved with tabular input file has wrong content!');
        }
    }

    /**
     * @depends testSaveFile
     */
    public function testUseDefaultFileUrl()
    {
        /* @var $model File|FileBehavior */

        $model = File::findOne(1);

        // Single string:
        $model->defaultFileUrl = null;
        $returnedFileWebSrc = $model->getFileUrl();
        $this->assertTrue(!empty($returnedFileWebSrc), 'Unable to get file web src with empty default one!');

        $testDefaultFileWebSrc = 'http://test/default/file/web/src';
        $model->defaultFileUrl = $testDefaultFileWebSrc;
        $returnedFileWebSrc = $model->getFileUrl();
        $this->assertEquals($returnedFileWebSrc, $testDefaultFileWebSrc, 'Default file web src does not used!');
    }

    /**
     * @depends testSaveFile
     */
    public function testOpenFile()
    {
        /* @var $model File|FileBehavior */
        /* @var $refreshedModel File|FileBehavior */

        $model = new File();
        $model->fileExtension = 'txt';
        $model->fileVersion = 1;
        $model->save(false);

        $resource = $model->openFile('w');
        $this->assertTrue(is_resource($resource));

        $fileContent = 'test file content';
        fwrite($resource, $fileContent);
        fclose($resource);

        $refreshedModel = File::findOne($model->id);

        $resource = $refreshedModel->openFile('r');
        $this->assertTrue(is_resource($resource));

        $this->assertEquals($fileContent, stream_get_contents($resource));
        fclose($resource);
    }
}