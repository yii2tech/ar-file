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
 * TransformFileBehavior is an enhanced version of the [[FileBehavior]] developed for the managing files,
 * which require some post processing.
 * Behavior allows to set up several different transformations for the file, so actually several files will
 * be related to the one record in the database table.
 * You can set up the [[transformCallback]] in order to specify transformation method(s).
 *
 * Note: you can always use [[saveFile()]] method to attach any file (not just uploaded one) to the model.
 *
 * @see FileBehavior
 *
 * @property string $defaultFileTransformName name of the default file transformation.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class TransformFileBehavior extends FileBehavior
{
    /**
     * @inheritdoc
     */
    public $subDirTemplate = '{^^pk}/{^pk}/{pk}';
    /**
     * @var array determines all possible file transformations.
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
    public $fileTransformations = [];
    /**
     * @var callable a PHP callback, which will be called while file transforming. The signature of the callback should
     * be following:
     *
     * ```php
     * function(string $sourceFileName, string $destinationFileName, mixed $transformationSettings) {
     *     //return boolean;
     * }
     * ```
     *
     * Callback should return boolean, which indicates whether transformation was successful or not.
     */
    public $transformCallback;
    /**
     * @var array|callable|null file extension specification for the file transformation results.
     * This value can be an array in format: [transformationName => fileExtension], for example:
     *
     * ```php
     * [
     *     'preview' => 'jpg',
     *     'archive' => 'zip',
     * ]
     * ```
     *
     * Each extension specification can be a PHP callback, which accepts original extension and should return actual.
     * For example:
     *
     * ```php
     * [
     *     'image' => function ($fileExtension) {
     *         return in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif']) ? $fileExtension : 'jpg';
     *     },
     * ]
     * ```
     *
     * You may specify this field as a single PHP callback of following signature:
     *
     * ```php
     * function (string $fileExtension, string $transformationName) {
     *     //return string actual extension;
     * }
     * ```
     *
     * @since 1.0.2
     */
    public $transformationFileExtensions;
    /**
     * @var string path, which should be used for storing temporary files during transformation.
     * If not set, default one will be composed inside '@runtime' directory.
     * Path aliases like '@webroot' and '@runtime' can be used here.
     */
    public $transformationTempFilePath;
    /**
     * @var string|array URL(s), which is used to set up web links, which will be returned if requested file does not exists.
     * If may specify this parameter as string it will be considered as web link and will be used for all transformations.
     * For example: 'http://www.myproject.com/materials/default/image.jpg'
     * If you specify this parameter as an array, its key will be considered as transformation name, while value - as web link.
     * For example:
     *
     * ```php
     * [
     *     'full' => 'http://www.myproject.com/materials/default/full.jpg',
     *     'thumbnail' => 'http://www.myproject.com/materials/default/thumbnail.jpg',
     * ]
     * ```
     */
    public $defaultFileUrl = [];

    /**
     * @var string name of the file transformation, which should be used by default, if no specific transformation name given.
     */
    private $_defaultFileTransformName;


    /**
     * @param string $defaultFileTransformName name of the default file transformation.
     */
    public function setDefaultFileTransformName($defaultFileTransformName)
    {
        $this->_defaultFileTransformName = $defaultFileTransformName;
    }

    /**
     * @return string name of the default file transformation.
     */
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
        $fileTransformations = $this->ensureFileTransforms();
        if (isset($fileTransformations[0])) {
            return $fileTransformations[0];
        }
        $transformNames = array_keys($fileTransformations);
        return array_shift($transformNames);
    }

    /**
     * Returns the default file URL.
     * @param string $name file transformation name.
     * @return string default file URL.
     */
    public function getDefaultFileUrl($name = null)
    {
        if (empty($this->defaultFileUrl)) {
            return null;
        }

        if (is_array($this->defaultFileUrl)) {
            if (isset($this->defaultFileUrl[$name])) {
                return $this->defaultFileUrl[$name];
            }
            reset($this->defaultFileUrl);
            return current($this->defaultFileUrl);
        }

        return $this->defaultFileUrl;
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
        $fileTransformName = $this->fetchFileTransformName($fileTransformName);
        $fileNamePrefix = '_' . $fileTransformName;
        if (is_null($fileVersion)) {
            $fileVersion = $this->getCurrentFileVersion();
        }

        $fileExtension = $this->getActualFileExtension($fileExtension, $fileTransformName);

        return $this->getFileBaseName() . $fileNamePrefix . '_' . $fileVersion . '.' . $fileExtension;
    }

    /**
     * Returns actual file extension for the particular transformation taking in account value of [[transformationFileExtensions]].
     * @param string|null $fileExtension original file extension.
     * @param string $fileTransformName file transformation name.
     * @return string actual file extension to be used.
     * @since 1.0.2
     */
    private function getActualFileExtension($fileExtension, $fileTransformName)
    {
        if ($fileExtension === null) {
            $fileExtension = $this->owner->getAttribute($this->fileExtensionAttribute);
        }

        if ($this->transformationFileExtensions === null) {
            return $fileExtension;
        }

        if (is_callable($this->transformationFileExtensions)) {
            return call_user_func($this->transformationFileExtensions, $fileExtension, $fileTransformName);
        }

        if (isset($this->transformationFileExtensions[$fileTransformName])) {
            if (is_string($this->transformationFileExtensions[$fileTransformName])) {
                return $this->transformationFileExtensions[$fileTransformName];
            }
            return call_user_func($this->transformationFileExtensions[$fileTransformName], $fileExtension);
        }

        return $fileExtension;
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
        $fileName = $this->getFileSelfName($fileTransformName, $fileVersion, $fileExtension);
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
     * Returns the [[fileTransformations]] value, making sure it is valid.
     * @throws InvalidConfigException if file transforms value is invalid.
     * @return array file transforms.
     */
    protected function ensureFileTransforms()
    {
        $fileTransformations = $this->fileTransformations;
        if (empty($fileTransformations)) {
            throw new InvalidConfigException('File transformations list is empty.');
        }
        return $fileTransformations;
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
        $fileTransformations = $this->ensureFileTransforms();

        $fileStorageBucket = $this->ensureFileStorageBucket();
        $result = true;
        foreach ($fileTransformations as $fileTransformName => $fileTransform) {
            if (!is_array($fileTransform) && is_numeric($fileTransformName)) {
                $fileTransformName = $fileTransform;
            }

            $fileFullName = $this->getFileFullName($fileTransformName, $fileVersion, $fileExtension);

            if (is_array($fileTransform)) {
                $transformTempFilePath = $this->ensureTransformationTempFilePath();
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
     * Ensures [[transformationTempFilePath]] exist and is writeable.
     * @throws InvalidConfigException if fails.
     * @return string temporary full file path.
     */
    protected function ensureTransformationTempFilePath()
    {
        if ($this->transformationTempFilePath === null) {
            $filePath = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . StringHelper::basename(get_class($this)) . DIRECTORY_SEPARATOR . StringHelper::basename(get_class($this->owner));
            $this->transformationTempFilePath = $filePath;
        } else {
            $filePath = Yii::getAlias($this->transformationTempFilePath);
        }

        if (!FileHelper::createDirectory($filePath)) {
            throw new InvalidConfigException("Unable to resolve temporary file path: '{$filePath}'!");
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
        $fileTransformations = $this->ensureFileTransforms();
        $result = true;
        $fileStorageBucket = $this->ensureFileStorageBucket();
        foreach ($fileTransformations as $fileTransformName => $fileTransform) {
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
     * @param mixed $transformationSettings is the transform settings data, its value is retrieved from [[fileTransformations]]
     * @return boolean success.
     */
    protected function transformFile($sourceFileName, $destinationFileName, $transformationSettings)
    {
        $arguments = func_get_args();
        return call_user_func_array($this->transformCallback, $arguments);
    }

    /**
     * Re-saves associated file, regenerating all available file transformations.
     * This method is useful in case settings for some transformations have been changed and you need to update existing records.
     * Note that this method will increment the file version.
     * @param string|null $sourceTransformationName name of the file transformation, which should be used as source file,
     * if not set - default transformation will be used.
     * @return boolean success.
     * @since 1.0.2
     */
    public function regenerateFileTransformations($sourceTransformationName = null)
    {
        $fileFullName = $this->getFileFullName($sourceTransformationName);
        $fileStorageBucket = $this->ensureFileStorageBucket();

        $tmpFileName = tempnam(Yii::getAlias('@runtime'), 'tmp_' . StringHelper::basename(get_class($this->owner)) . '_') . '.' . $this->owner->getAttribute($this->fileExtensionAttribute);
        $fileStorageBucket->copyFileOut($fileFullName, $tmpFileName);
        return $this->saveFile($tmpFileName, true);
    }

    // File Interface Function Shortcuts:

    /**
     * Checks if file related to the model exists.
     * @param string $name transformation name
     * @return boolean file exists.
     */
    public function fileExists($name = null)
    {
        $fileStorageBucket = $this->ensureFileStorageBucket();
        return $fileStorageBucket->fileExists($this->getFileFullName($name));
    }

    /**
     * Returns the content of the model related file.
     * @param string $name transformation name
     * @return string file content.
     */
    public function getFileContent($name = null)
    {
        $fileStorageBucket = $this->ensureFileStorageBucket();
        return $fileStorageBucket->getFileContent($this->getFileFullName($name));
    }

    /**
     * Returns full web link to the model's file.
     * @param string $name transformation name
     * @return string web link to file.
     */
    public function getFileUrl($name = null)
    {
        $fileStorageBucket = $this->ensureFileStorageBucket();
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