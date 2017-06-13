<?php

namespace yii2tech\tests\unit\ar\file;

use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;
use Yii;

/**
 * Base class for the test cases.
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockApplication();

        $this->setupTestDbData();

        $_FILES = [];
        UploadedFile::reset();

        FileHelper::createDirectory($this->getTestSourceBasePath());
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->destroyApplication();

        FileHelper::removeDirectory($this->getTestSourceBasePath());
        FileHelper::removeDirectory($this->getTestFileStorageBasePath());
    }

    /**
     * Populates Yii::$app with a new application
     * The application will be destroyed on tearDown() automatically.
     * @param array $config The application configuration, if needed
     * @param string $appClass name of the application class to create
     */
    protected function mockApplication($config = [], $appClass = '\yii\console\Application')
    {
        new $appClass(ArrayHelper::merge([
            'id' => 'testapp',
            'basePath' => __DIR__,
            'vendorPath' => $this->getVendorPath(),
            'components' => [
                'db' => [
                    'class' => 'yii\db\Connection',
                    'dsn' => 'sqlite::memory:',
                ],
                'fileStorage' => [
                    'class' => 'yii2tech\filestorage\local\Storage',
                    'basePath' => $this->getTestFileStorageBasePath(),
                    'baseUrl' => 'http://www.mydomain.com/files',
                    'filePermission' => 0777
                ],
            ],
        ], $config));
    }

    /**
     * @return string vendor path
     */
    protected function getVendorPath()
    {
        return dirname(__DIR__) . '/vendor';
    }

    /**
     * Destroys application in Yii::$app by setting it to null.
     */
    protected function destroyApplication()
    {
        Yii::$app = null;
    }

    /**
     * Setup tables for test ActiveRecord
     */
    protected function setupTestDbData()
    {
        $db = Yii::$app->getDb();

        // Structure :

        $table = 'File';
        $columns = [
            'id' => 'pk',
            'name' => 'string',
            'fileExtension' => 'string',
            'fileVersion' => 'integer',
        ];
        $db->createCommand()->createTable($table, $columns)->execute();

        $columns = array(
            'name' => 'test_name',
        );
        $db->createCommand()->insert($table, $columns);

        // Data :

        $db->createCommand()->batchInsert('File', ['name', 'fileExtension', 'fileVersion'], [
            ['test_name', '', ''],
        ])->execute();
    }

    /**
     * Returns the base path for the test files.
     * @return string test file base path.
     */
    protected function getTestSourceBasePath()
    {
        return Yii::getAlias('@yii2tech/tests/unit/ar/file/runtime/source') . '_' . getmypid();
    }

    /**
     * Returns the base path for the test files.
     * @return string test file base path.
     */
    protected function getTestFileStorageBasePath()
    {
        return Yii::getAlias('@yii2tech/tests/unit/ar/file/runtime/file-storage') . '_' . getmypid();
    }

    /**
     * Returns extension of the given file.
     * @param string $filename file name.
     * @return string file extension.
     */
    protected function getFileExtension($filename)
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Mocks up the global $_FILES with the given file data.
     * @param \yii\db\BaseActiveRecord|\yii2tech\ar\file\FileBehavior $model model instance.
     * @param string $fileName file self name.
     * @param string $fileContent file content.
     * @param int|bool $tabularIndex file input tabular index, if `false` - no index will be used.
     * @return bool success.
     */
    protected function mockUpUploadedFile($model, $fileName, $fileContent, $tabularIndex = false)
    {
        $modelName = $model->formName();
        $fileAttribute = $model->fileAttribute;

        $fullFileName = $this->saveTestFile($fileName, $fileContent);

        if ($tabularIndex === false) {
            $_FILES[$modelName]['name'][$fileAttribute] = basename($fullFileName);
            $_FILES[$modelName]['type'][$fileAttribute] = $this->getFileExtension($fullFileName);
            $_FILES[$modelName]['tmp_name'][$fileAttribute] = $fullFileName;
            $_FILES[$modelName]['error'][$fileAttribute] = UPLOAD_ERR_OK;
            $_FILES[$modelName]['size'][$fileAttribute] = filesize($fullFileName);
        } else {
            $_FILES[$modelName]['name'][$tabularIndex][$fileAttribute] = basename($fullFileName);
            $_FILES[$modelName]['type'][$tabularIndex][$fileAttribute] = $this->getFileExtension($fullFileName);
            $_FILES[$modelName]['tmp_name'][$tabularIndex][$fileAttribute] = $fullFileName;
            $_FILES[$modelName]['error'][$tabularIndex][$fileAttribute] = UPLOAD_ERR_OK;
            $_FILES[$modelName]['size'][$tabularIndex][$fileAttribute] = filesize($fullFileName);
        }

        return true;
    }

    /**
     * Saves the file inside test directory.
     * Returns the file absolute name.
     * @param string $fileSelfName file self name.
     * @param string $fileContent file content.
     * @return string file full name.
     */
    protected function saveTestFile($fileSelfName, $fileContent)
    {
        $fullFileName = $this->getTestSourceBasePath() . DIRECTORY_SEPARATOR . $fileSelfName;
        if (file_exists($fullFileName)) {
            unlink($fullFileName);
        }
        file_put_contents($fullFileName, $fileContent);
        return $fullFileName;
    }

    /**
     * Deletes the file inside test directory.
     * @param string $fileSelfName file self name.
     * @return bool success.
     */
    protected function deleteTestFile($fileSelfName)
    {
        $fullFileName = $this->getTestSourceBasePath() . DIRECTORY_SEPARATOR . $fileSelfName;
        if (file_exists($fullFileName)) {
            unlink($fullFileName);
        }
        return true;
    }
}