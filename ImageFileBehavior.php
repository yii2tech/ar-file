<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\ar\file;

use yii\base\InvalidConfigException;
use yii\imagine\Image;

/**
 * Extension of the [[TransformFileBehavior]] - behavior for the ActiveRecord.
 * ImageFileBehavior is developed for the managing image files.
 * Behavior allows to set up several different transformations for image, so actually several files will be related to the one record in the database table. 
 * You can set up the [[transformCallback]] in order to specify transformation method(s).
 * By default behavior attempts to call convert method of the [[imageFileConvertorComponentName]] application component.
 *
 * In order to specify image resizing, you should set [[fileTransforms]] field.
 * For example:
 *
 * ```php
 * [
 *     'full' => [800, 600],
 *     'thumbnail' => [200, 150]
 * ];
 * ```
 *
 * In order save original file without any transformations, set string value with native key.
 * For example:
 *
 * ```php
 * [
 *     'origin',
 *     'full' => [800, 600],
 *     'thumbnail' => [200, 150]
 * ];
 * ```
 *
 * Note: you can always use [[saveFile()]] method to attach any file (not just uploaded one) to the model.
 *
 * Attention: this extension requires the extension "yiisoft/yii2-imagine" to be attached to the application!
 *
 * @see TransformFileBehavior
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class ImageFileBehavior extends TransformFileBehavior
{
    /**
     * @var string name of the application component, which should be used to convert image files.
     */
    public $imageFileConvertorComponentName = 'imageFileConvertor';


    /**
     * @inheritdoc
     */
    protected function transformFile($sourceFileName, $destinationFileName, $transformSettings)
    {
        if ($this->transformCallback === null) {
            return $this->transformImageFileResize($sourceFileName, $destinationFileName, $transformSettings);
        }
        return parent::transformFile($sourceFileName, $destinationFileName, $transformSettings);
    }

    /**
     * Transforms source file to destination file according to the transformation settings,
     * using ImageMagic tool.
     * @param string $sourceFileName is the full source file system name.
     * @param string $destinationFileName is the full destination file system name.
     * @param array $transformSettings is the transform settings data, it should be the pair: imageWidth & imageHeight,
     * For example: `[800, 600]`
     * @throws InvalidConfigException on invalid transform settings.
     * @return boolean success.
     */
    protected function transformImageFileResize($sourceFileName, $destinationFileName, $transformSettings)
    {
        if (!is_array($transformSettings)) {
            throw new InvalidConfigException('Wrong transform settings are passed to "' . get_class($this) . '::' . __FUNCTION__ . '"');
        }
        list($width, $height) = array_values($transformSettings);
        Image::thumbnail($sourceFileName, $width, $height)->save($destinationFileName);
        return true;
    }
}