<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\ar\file;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use yii\helpers\StringHelper;

/**
 * Extension of the [[FileBehavior]] - behavior for the CActiveRecord.
 * TransformFileBehavior is developed for the managing files, which require some post processing.
 * Behavior allows to set up several different transformations for the file, so actually several files will
 * be related to the one record in the database table.
 * You can set up the [[transformCallback]] in order to specify transformation method(s).
 *
 * Note: you can always use [[saveFile()]] method to attach any file (not just uploaded one) to the model.
 *
 * @see FileBehavior
 *
 * @property string $defaultFileTransformName public alias of {@link _defaultFileTransformName}.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class TransformFileBehavior extends FileBehavior
{
    /**
     * @var array, which determines all possible file transformations.
     * The key of array element is the name of transformation and will be used to create file name.
     * The value is an array of parameters for transformation. Its value depends on which [[transformCallback]] you are using.
     * If you wish to save original file without transformation, specify a key without value.
     * For example:
     *
     * ```php
     * [
     *     'origin',
     *     'main' => [...],
     *     'light' => [...],
     * ];
     * ```
     */
    public $fileTransforms = [];
    /**
     * @var callable a PHP callback, which will be called while file transforming.
     */
    public $transformCallback;
    /**
     * @var string|array URL(s), which is used to set up web links, which will be returned if requested file does not exists.
     * If may specify this parameter as string it will be considered as web link and will be used for all transformations.
     * For example:  'http://www.myproject.com/materials/default/image.jpg'
     * If you specify this parameter as an array, its key will be considered as transformation name, while value - as web link.
     * For example:
     * [
     *     'full'=> 'http://www.myproject.com/materials/default/full.jpg',
     *     'thumbnail'=> 'http://www.myproject.com/materials/default/thumbnail.jpg',
     * ]
     */
    public $defaultFileUrl = [];

    /**
     * @var string name of the file transformation, which should be used by default,
     * if no specific transformation name given.
     */
    private $_defaultFileTransformName;


    public function setDefaultFileTransformName($defaultFileTransformName)
    {
        $this->_defaultFileTransformName = $defaultFileTransformName;
        return true;
    }

    public function getDefaultFileTransformName()
    {
        if (empty($this->_defaultFileTransformName)) {
            $this->_defaultFileTransformName = $this->initDefaultFileTransformName();
        }
        return $this->_defaultFileTransformName;
    }

    /**
     * Initializes the default [[defaultFileTransform]] value.
     * @return string transformation name.
     */
    protected function initDefaultFileTransformName()
    {
        $fileTransforms = $this->ensureFileTransforms();
        if (isset($fileTransforms[0])) {
            return $fileTransforms[0];
        } else {
            $transformNames = array_keys($fileTransforms);
            return array_shift($transformNames);
        }
    }

    /**
     * Returns the default file URL.
     * @param string $name file transformation name.
     * @return string default file URL.
     */
    public function getDefaultFileUrl($name = null)
    {
        if (is_array($this->defaultFileUrl)) {
            if (!empty($name)) {
                return $this->defaultFileUrl[$name];
            } else {
                reset($this->defaultFileUrl);
                return current($this->defaultFileUrl);
            }
        } else {
            return $this->defaultFileUrl;
        }
    }

    /**
     * Creates file itself name (without path) including version and extension.
     * This method overrides parent implementation in order to include transformation name.
     * @param string $fileTransformName image transformation name.
     * @param integer $fileVersion file version number.
     * @param string $fileExtension file extension.
     * @return string file self name.
     */
    public function getFileSelfName($fileTransformName = null, $fileVersion = null, $fileExtension = null)
    {
        $owner = $this->owner;
        $fileTransformName = $this->fetchFileTransformName($fileTransformName);
        $fileNamePrefix = '_' . $fileTransformName;
        if (is_null($fileVersion)) {
            $fileVersion = $this->getCurrentFileVersion();
        }
        if (is_null($fileExtension)) {
            $fileExtension = $owner->getAttribute($this->fileExtensionAttribute);
        }
        return $this->getFileBaseName() . $fileNamePrefix . '_' . $fileVersion . '.' . $fileExtension;
    }

    /**
     * Creates the file name in the file storage.
     * This name contains the sub directory, resolved by [[subDirTemplate]].
     * @param string $fileTransformName file transformation name.
     * @param integer $fileVersion file version number.
     * @param string $fileExtension file extension.
     * @return string file full name.
     */
    public function getFileFullName($fileTransformName = null, $fileVersion = null, $fileExtension = null)
    {
        $fileName = $this->getFileSelfName($fileTransformName, $fileVersion,$fileExtension);
        $subDir = $this->getActualSubDir();
        if (!empty($subDir)) {
            $fileName = $subDir . DIRECTORY_SEPARATOR . $fileName;
        }
        return $fileName;
    }

    /**
     * Fetches the value of file transform name.
     * Returns default file transform name if null incoming one is given.
     * @param string|null $fileTransformName file transforms name.
     * @return string actual file transform name.
     */
    protected function fetchFileTransformName($fileTransformName = null)
    {
        if (is_null($fileTransformName)) {
            $fileTransformName = $this->getDefaultFileTransformName();
        }
        return $fileTransformName;
    }

    /**
     * Returns the [[fileTransforms]] value, making sure it is valid.
     * @throws InvalidConfigException if file transforms value is invalid.
     * @return array file transforms.
     */
    protected function ensureFileTransforms()
    {
        $fileTransforms = $this->fileTransforms;
        if (empty($fileTransforms)) {
            throw new InvalidConfigException('File transformations list is empty.');
        }
        return $fileTransforms;
    }

    /**
     * Overridden.
     * Creates the file for the model from the source file.
     * File version and extension are passed to this method.
     * Parent method is overridden in order to save several different files
     * per one particular model.
     * @param string $sourceFileName - source full file name.
     * @param integer $fileVersion - file version number.
     * @param string $fileExtension - file extension.
     * @return boolean success.
     */
    protected function newFile($sourceFileName, $fileVersion, $fileExtension)
    {
        $fileTransforms = $this->ensureFileTransforms();

        $fileStorageBucket = $this->getFileStorageBucket();
        $result = true;
        foreach ($fileTransforms as $fileTransformName => $fileTransform) {
            if (!is_array($fileTransform) && is_numeric($fileTransformName)) {
                $fileTransformName = $fileTransform;
            }

            $fileFullName = $this->getFileFullName($fileTransformName, $fileVersion, $fileExtension);

            if (is_array($fileTransform)) {
                $transformTempFilePath = $this->resolveTransformTempFilePath();
                $tempTransformFileName = basename($fileFullName);
                $tempTransformFileName = uniqid(rand()) . '_' . $tempTransformFileName;
                $tempTransformFileName = $transformTempFilePath . DIRECTORY_SEPARATOR . $tempTransformFileName;
                $resizeResult = $this->transformFile($sourceFileName, $tempTransformFileName, $fileTransform);
                if ($resizeResult) {
                    $copyResult = $fileStorageBucket->copyFileIn($tempTransformFileName, $fileFullName);
                    $result = $result && $copyResult;
                } else {
                    $result = $result && $resizeResult;
                }
                if (file_exists($tempTransformFileName)) {
                    unlink($tempTransformFileName);
                }
            } else {
                $copyResult = $fileStorageBucket->copyFileIn($sourceFileName, $fileFullName);
                $result = $result && $copyResult;
            }
        }
        return $result;
    }

    /**
     * Generates the temporary file path for the file transformations
     * and makes sure it exists.
     * @throws \Exception if fails.
     * @return string temporary full file path.
     */
    protected function resolveTransformTempFilePath()
    {
        $filePath = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . StringHelper::basename(get_class($this)) . DIRECTORY_SEPARATOR . StringHelper::basename(get_class($this->owner));
        FileHelper::createDirectory($filePath);

        if (!file_exists($filePath) || !is_dir($filePath)) {
            throw new \Exception("Unable to resolve temporary file path: '{$filePath}'!");
        } elseif (!is_writable($filePath)) {
            throw new \Exception("Path: '{$filePath}' should be writeable!");
        }
        return $filePath;
    }

    /**
     * Overridden.
     * Removes all files associated with the owner model.
     * @return boolean success.
     */
    public function deleteFile()
    {
        $fileTransforms = $this->ensureFileTransforms();
        $result = true;
        $fileStorageBucket = $this->getFileStorageBucket();
        foreach ($fileTransforms as $fileTransformName => $fileTransform) {
            if (!is_array($fileTransform) && is_numeric($fileTransformName)) {
                $fileTransformName = $fileTransform;
            }
            $fileName = $this->getFileFullName($fileTransformName);
            if ($fileStorageBucket->fileExists($fileName)) {
                $fileDeleteResult = $fileStorageBucket->deleteFile($fileName);
                $result = $result && $fileDeleteResult;
            }
        }
        return $result;
    }

    /**
     * Transforms source file to destination file according to the transformation settings.
     * @param string $sourceFileName is the full source file system name.
     * @param string $destinationFileName is the full destination file system name.
     * @param mixed $transformSettings is the transform settings data, its value is retrieved from {@link fileTransforms}
     * @return boolean success.
     */
    protected function transformFile($sourceFileName, $destinationFileName, $transformSettings)
    {
        $arguments = func_get_args();
        return call_user_func_array($this->transformCallback, $arguments);
    }

    // File Interface Function Shortcuts:

    /**
     * Checks if file related to the model exists.
     * @param string $name transformation name
     * @return boolean file exists.
     */
    public function fileExists($name = null)
    {
        $fileStorageBucket = $this->getFileStorageBucket();
        return $fileStorageBucket->fileExists($this->getFileFullName($name));
    }

    /**
     * Returns the content of the model related file.
     * @param string $name transformation name
     * @return string file content.
     */
    public function getFileContent($name = null)
    {
        $fileStorageBucket = $this->getFileStorageBucket();
        return $fileStorageBucket->getFileContent($this->getFileFullName($name));
    }

    /**
     * Returns full web link to the model's file.
     * @param string $name transformation name
     * @return string web link to file.
     */
    public function getFileUrl($name = null)
    {
        $fileStorageBucket = $this->getFileStorageBucket();
        $fileFullName = $this->getFileFullName($name);
        $defaultFileUrl = $this->getDefaultFileUrl($name);
        if (!empty($defaultFileUrl)) {
            if (!$fileStorageBucket->fileExists($fileFullName)) {
                return $defaultFileUrl;
            }
        }
        return $fileStorageBucket->getFileUrl($fileFullName);
    }
}
