Yii 2 ActiveRecord File Attachment extension Change Log
=======================================================

1.0.3 under development
-----------------------

- Enh #6: Added `FileBehavior::openFile()` method as a shortcut to `yii2tech\filestorage\BucketInterface::openFile()` (klimov-paul)
- Enh #9: `FileBehavior::$subDirTemplate` now accepts the PHP callback and supports additional placeholders `{__basemodel__}` and `{__modelid__}` (nexen2, klimov-paul)


1.0.2, October 7, 2016
----------------------

- Bug #4: Fixed `TransformFileBehavior::getFileUrl()` triggers `E_NOTICE` in case `defaultFileUrl` is an empty array (klimov-paul)
- Enh #3: Added support for transformed file extension variation via `TransformFileBehavior::transformationFileExtensions` (klimov-paul)
- Enh #5: Added `TransformFileBehavior::regenerateFileTransformations()` method, allowing regeneration of the file transformations (klimov-paul)


1.0.1, February 14, 2016
------------------------

- Bug #1: Fixed required version of "yii2tech/file-storage" preventing stable release composer install (klimov-paul)


1.0.0, February 10, 2016
------------------------

- Initial release.
