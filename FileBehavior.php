<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\ar\file;

use Yii;
use yii\base\Behavior;
use yii\base\UnknownPropertyException;
use yii\db\BaseActiveRecord;
use yii\di\Instance;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\web\UploadedFile;
use yii2tech\filestorage\BucketInterface;
use yii2tech\filestorage\StorageInterface;

/**
 * Behavior for the [[BaseActiveRecord]], which allows to save a single file per each table record.
 * Behavior tracks the file extension and manage file version in order to prevent cache problems.
 * Due to this the database table, which the model refers to, must contain fields [[fileExtensionAttribute]] and [[fileVersionAttribute]].
 * On the model save behavior will automatically search for the attached file in $_FILES.
 * However you can manipulate attached file using property [[uploadedFile]].
 * For the tabular file input use [[fileTabularInputIndex]] property.
 *
 * Note: you can always use [[saveFile()]] method to attach any file (not just uploaded one) to the model.
 *
 * Attention: this extension requires the extension "yii2tech/file-storage" to be attached to the application!
 * Files will be saved using file storage component.
 * 
 * @see StorageInterface
 * @see BucketInterface
 *
 * @property UploadedFile|string|null $uploadedFile related uploaded file
 * @property BaseActiveRecord $owner owner ActiveRecord instance.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class FileBehavior extends Behavior
{
    /**
     * @var string name of virtual model's attribute, which will be used
     * to fetch file uploaded from the web form.
     * Use value of this attribute to create web form file input field.
     */
    public $fileAttribute = 'file';
    /**
     * @var string name of the file storage application component.
     */
    public $fileStorage = 'fileStorage';
    /**
     * @var string|BucketInterface name of the file storage bucket, which stores the related files or
     * bucket instance itself.
     * If empty, bucket name will be generated automatically using owner class name and [[fileAttribute]].
     */
    public $fileStorageBucket;
    /**
     * @var string template of all sub directories, which will store a particular
     * model instance's files. Value of this parameter will be parsed per each model instance.
     * You can use model attribute names to create sub directories, for example place all transformed
     * files in the subfolder with the name of model id. To use a dynamic value of attribute
     * place attribute name in curly brackets, for example: {id}.
     * You may also specify special placeholders:
     *
     * - {pk} - resolved as primary key value of the owner model,
     * - {__model__} - resolved as class base name of the owner model,
     * - {__file__} - resolved as value of [[fileAttribute]].
     *
     * You may place symbols "^" before any placeholder name, such placeholder will be resolved as single
     * symbol of the normal value. Number of symbol determined by count of "^".
     * For example: if model id equal to 54321, placeholder {^id} will be resolved as "5", {^^id} - as "4" and so on.
     * Example value:
     * '{__model__}/{__file__}/{groupId}/{^pk}/{pk}'
     */
    public $subDirTemplate = '{^^pk}/{^pk}';
    /**
     * @var string name of model's attribute, which will be used to store file extension.
     * Corresponding model's attribute should be a string type.
     */
    public $fileExtensionAttribute = 'fileExtension';
    /**
     * @var string name of model's attribute, which will be used to store file version number.
     * Corresponding model's attribute should be a string or integer type.
     */
    public $fileVersionAttribute = 'fileVersion';
    /**
     * @var integer index of the HTML input file field in case of tabular input (input name has format "ModelName[$i][file]").
     * Note: after owner is saved this property will be reset.
     */
    public $fileTabularInputIndex;
    /**
     * @var string URL which is used to set up web links, which will be returned, if requested file does not exists.
     * For example: 'http://www.myproject.com/materials/default/image.jpg'
     */
    public $defaultFileUrl;
    /**
     * @var boolean indicates if behavior will attempt to fetch uploaded file automatically from the HTTP request.
     */
    public $autoFetchUploadedFile = true;

    /**
     * @var UploadedFile instance of [[UploadedFile]], allows to save file,
     * passed through the web form.
     */
    private $_uploadedFile;

    // Set / Get:

    /**
     * @param UploadedFile|string|null $uploadedFile related uploaded file
     */
    public function setUploadedFile($uploadedFile)
    {
        $this->_uploadedFile = $uploadedFile;
    }

    /**
     * @return UploadedFile|null related uploaded file
     */
    public function getUploadedFile()
    {
        if (!is_object($this->_uploadedFile)) {
            $this->_uploadedFile = $this->ensureUploadedFile($this->_uploadedFile);
        }
        return $this->_uploadedFile;
    }

    /**
     * Returns the file storage bucket for the files by name given with [[fileStorageBucket]].
     * If no bucket exists attempts to create it.
     * @return BucketInterface file storage bucket instance.
     */
    public function ensureFileStorageBucket()
    {
        if (!is_object($this->fileStorageBucket)) {
            /* @var StorageInterface $fileStorage */
            $fileStorage = Instance::ensure($this->fileStorage, 'yii2tech\filestorage\StorageInterface');

            if ($this->fileStorageBucket === null) {
                $bucketName = $this->defaultFileStorageBucketName();
            } else {
                $bucketName = $this->fileStorageBucket;
            }
            if (!$fileStorage->hasBucket($bucketName)) {
                $fileStorage->addBucket($bucketName);
            }
            $this->fileStorageBucket = $fileStorage->getBucket($bucketName);
        }
        return $this->fileStorageBucket;
    }

    /**
     * Composes default [[fileStorageBucket]] name, using owner class name and [[fileAttribute]].
     * @return string bucket name.
     */
    protected function defaultFileStorageBucketName()
    {
        return Inflector::camel2id(StringHelper::basename(get_class($this->owner)), '-');
    }

    // SubDir Template:

    /**
     * Gets file storage sub dirs path, resolving [[subDirTemplate]].
     * @return string actual sub directory string.
     */
    public function getActualSubDir()
    {
        $subDirTemplate = $this->subDirTemplate;
        if (empty($subDirTemplate)) {
            return $subDirTemplate;
        }
        $result = preg_replace_callback('/{(\^*(\w+))}/', [$this, 'getSubDirPlaceholderValue'], $subDirTemplate);
        return $result;
    }

    /**
     * Internal callback function for [[getActualSubDir()]].
     * @param array $matches - set of regular expression matches.
     * @return string replacement for the match.
     */
    protected function getSubDirPlaceholderValue($matches)
    {
        $placeholderName = $matches[1];
        $placeholderPartSymbolPosition = strspn($placeholderName, '^') - 1;
        if ($placeholderPartSymbolPosition >= 0) {
            $placeholderName = $matches[2];
        }

        switch ($placeholderName) {
            case 'pk': {
                $placeholderValue = $this->getPrimaryKeyStringValue();
                break;
            }
            case '__model__': {
                $owner = $this->owner;
                $placeholderValue = str_replace('\\', '_', get_class($owner));
                break;
            }
            case '__file__': {
                $placeholderValue = $this->fileAttribute;
                break;
            }
            default: {
                $owner = $this->owner;
                try {
                    $placeholderValue = $owner->$placeholderName;
                } catch (UnknownPropertyException $exception) {
                    $placeholderValue = $placeholderName;
                }
            }
        }

        if ($placeholderPartSymbolPosition >= 0) {
            if ($placeholderPartSymbolPosition < strlen($placeholderValue)) {
                $placeholderValue = substr($placeholderValue, $placeholderPartSymbolPosition, 1);
            } else {
                $placeholderValue = '0';
            }
        }

        return $placeholderValue;
    }

    // Service:

    /**
     * Creates string representation of owner model primary key value,
     * handles case when primary key is complex and consist of several fields.
     * @return string representation of owner model primary key value.
     */
    protected function getPrimaryKeyStringValue()
    {
        $owner = $this->owner;
        $primaryKey = $owner->getPrimaryKey();
        if (is_array($primaryKey)) {
            return implode('_', $primaryKey);
        }
        return $primaryKey;
    }

    /**
     * Creates base part of the file name.
     * This value will be append with the version and extension for the particular file.
     * @return string file name's base part.
     */
    protected function getFileBaseName()
    {
        return $this->getPrimaryKeyStringValue();
    }

    /**
     * Returns current version value of the model's file.
     * @return integer current version of model's file.
     */
    public function getCurrentFileVersion()
    {
        $owner = $this->owner;
        return $owner->getAttribute($this->fileVersionAttribute);
    }

    /**
     * Returns next version value of the model's file.
     * @return integer next version of model's file.
     */
    public function getNextFileVersion()
    {
        return $this->getCurrentFileVersion() + 1;
    }

    /**
     * Creates file itself name (without path) including version and extension.
     * @param integer $fileVersion file version number.
     * @param string $fileExtension file extension.
     * @return string file self name.
     */
    public function getFileSelfName($fileVersion = null, $fileExtension = null)
    {
        $owner = $this->owner;
        if ($fileVersion === null) {
            $fileVersion = $this->getCurrentFileVersion();
        }
        if ($fileExtension === null) {
            $fileExtension = $owner->getAttribute($this->fileExtensionAttribute);
        }
        return $this->getFileBaseName() . '_' . $fileVersion . '.' . $fileExtension;
    }

    /**
     * Creates the file name in the file storage.
     * This name contains the sub directory, resolved by [[subDirTemplate]].
     * @param integer $fileVersion file version number.
     * @param string $fileExtension file extension.
     * @return string file full name.
     */
    public function getFileFullName($fileVersion = null, $fileExtension = null)
    {
        $fileName = $this->getFileSelfName($fileVersion, $fileExtension);
        $subDir = $this->getActualSubDir();
        if (!empty($subDir)) {
            $fileName = $subDir . DIRECTORY_SEPARATOR . $fileName;
        }
        return $fileName;
    }

    // Main File Operations:

    /**
     * Associate new file with the owner model.
     * This method will determine new file version and extension, and will update the owner
     * model correspondingly.
     * @param string|UploadedFile $sourceFileNameOrUploadedFile file system path to source file or [[UploadedFile]] instance.
     * @param boolean $deleteSourceFile determines would the source file be deleted in the process or not,
     * if null given file will be deleted if it was uploaded via POST.
     * @return boolean save success.
     */
    public function saveFile($sourceFileNameOrUploadedFile, $deleteSourceFile = null)
    {
        $this->deleteFile();

        $fileVersion = $this->getNextFileVersion();

        if (is_object($sourceFileNameOrUploadedFile)) {
            $sourceFileName = $sourceFileNameOrUploadedFile->tempName;
            $fileExtension = $sourceFileNameOrUploadedFile->getExtension();
        } else {
            $sourceFileName = $sourceFileNameOrUploadedFile;
            $fileExtension = strtolower(pathinfo($sourceFileName, PATHINFO_EXTENSION));
        }

        $result = $this->newFile($sourceFileName, $fileVersion, $fileExtension);

        if ($result) {
            if ($deleteSourceFile === null) {
                $deleteSourceFile = is_uploaded_file($sourceFileName);
            }
            if ($deleteSourceFile) {
                unlink($sourceFileName);
            }

            $owner = $this->owner;

            $attributes = [
                $this->fileVersionAttribute => $fileVersion,
                $this->fileExtensionAttribute => $fileExtension
            ];
            $owner->updateAttributes($attributes);
        }

        return $result;
    }

    /**
     * Creates the file for the model from the source file.
     * File version and extension are passed to this method.
     * @param string $sourceFileName - source full file name.
     * @param integer $fileVersion - file version number.
     * @param string $fileExtension - file extension.
     * @return boolean success.
     */
    protected function newFile($sourceFileName, $fileVersion, $fileExtension)
    {
        $fileFullName = $this->getFileFullName($fileVersion, $fileExtension);
        $fileStorageBucket = $this->ensureFileStorageBucket();
        return $fileStorageBucket->copyFileIn($sourceFileName, $fileFullName);
    }

    /**
     * Removes file associated with the owner model.
     * @return boolean success.
     */
    public function deleteFile()
    {
        $fileStorageBucket = $this->ensureFileStorageBucket();
        $fileName = $this->getFileFullName();
        if ($fileStorageBucket->fileExists($fileName)) {
            return $fileStorageBucket->deleteFile($fileName);
        }
        return true;
    }

    /**
     * Finds the uploaded through the web file, creating [[UploadedFile]] instance.
     * If parameter $fullFileName is passed, creates a mock up instance of [[UploadedFile]] from the local file,
     * passed with this parameter.
     * @param UploadedFile|string|null $uploadedFile - source full file name for the [[UploadedFile]] mock up.
     * @return UploadedFile|null uploaded file.
     */
    protected function ensureUploadedFile($uploadedFile = null)
    {
        if ($uploadedFile instanceof UploadedFile) {
            return $uploadedFile;
        }

        if (!empty($uploadedFile)) {
            return new UploadedFile([
                'name' => basename($uploadedFile),
                'tempName' => $uploadedFile,
                'type' => FileHelper::getMimeType($uploadedFile),
                'size' => filesize($uploadedFile),
                'error' => UPLOAD_ERR_OK
            ]);
        }

        if ($this->autoFetchUploadedFile) {
            $owner = $this->owner;
            $fileAttributeName = $this->fileAttribute;
            $tabularInputIndex = $this->fileTabularInputIndex;
            if ($tabularInputIndex !== null) {
                $fileAttributeName = "[{$tabularInputIndex}]{$fileAttributeName}";
            }
            $uploadedFile = UploadedFile::getInstance($owner, $fileAttributeName);
            if (is_object($uploadedFile)) {
                if (!$uploadedFile->getHasError() && !file_exists($uploadedFile->tempName)) {
                    // uploaded file has been already processed:
                    return null;
                } else {
                    return $uploadedFile;
                }
            }
        }

        return null;
    }

    // File Interface Function Shortcuts:

    /**
     * Checks if file related to the model exists.
     * @return boolean file exists.
     */
    public function fileExists()
    {
        $fileStorageBucket = $this->ensureFileStorageBucket();
        return $fileStorageBucket->fileExists($this->getFileFullName());
    }

    /**
     * Returns the content of the model related file.
     * @return string file content.
     */
    public function getFileContent()
    {
        $fileStorageBucket = $this->ensureFileStorageBucket();
        return $fileStorageBucket->getFileContent($this->getFileFullName());
    }

    /**
     * Returns full web link to the model related file.
     * @return string web link to file.
     */
    public function getFileUrl()
    {
        $fileStorageBucket = $this->ensureFileStorageBucket();
        $fileFullName = $this->getFileFullName();
        if ($this->defaultFileUrl !== null) {
            if (!$fileStorageBucket->fileExists($fileFullName)) {
                return $this->defaultFileUrl;
            }
        }
        return $fileStorageBucket->getFileUrl($fileFullName);
    }

    // Property Access Extension:

    /**
     * PHP getter magic method.
     * This method is overridden so that variation attributes can be accessed like properties.
     *
     * @param string $name property name
     * @throws UnknownPropertyException if the property is not defined
     * @return mixed property value
     */
    public function __get($name)
    {
        try {
            return parent::__get($name);
        } catch (UnknownPropertyException $exception) {
            if ($this->owner !== null) {
                if ($name === $this->fileAttribute) {
                    return $this->getUploadedFile();
                }
            }
            throw $exception;
        }
    }

    /**
     * PHP setter magic method.
     * This method is overridden so that variation attributes can be accessed like properties.
     * @param string $name property name
     * @param mixed $value property value
     * @throws UnknownPropertyException if the property is not defined
     */
    public function __set($name, $value)
    {
        try {
            parent::__set($name, $value);
        } catch (UnknownPropertyException $exception) {
            if ($this->owner !== null) {
                if ($name === $this->fileAttribute) {
                    $this->setUploadedFile($value);
                    return;
                }
            }
            throw $exception;
        }
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        if (parent::canGetProperty($name, $checkVars)) {
            return true;
        }
        if ($this->owner === null) {
            return false;
        }
        return ($name === $this->fileAttribute);
    }

    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        if (parent::canSetProperty($name, $checkVars)) {
            return true;
        }
        if ($this->owner === null) {
            return false;
        }
        return ($name === $this->fileAttribute);
    }

    // Events:

    /**
     * Declares events and the corresponding event handler methods.
     * @return array events (array keys) and the corresponding event handler methods (array values).
     */
    public function events()
    {
        return [
            BaseActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    /**
     * This event raises after owner saved.
     * It saves uploaded file if it exists.
     * @param \yii\base\Event $event event instance.
     */
    public function afterSave($event)
    {
        $uploadedFile = $this->getUploadedFile();
        if (is_object($uploadedFile) && !$uploadedFile->getHasError()) {
            $this->saveFile($uploadedFile);
        }
        $this->setUploadedFile(null);
    }

    /**
     * This event raises before owner deleted.
     * It deletes related file.
     * @param \yii\base\Event $event event instance.
     */
    public function afterDelete($event)
    {
        $this->deleteFile();
    }
}