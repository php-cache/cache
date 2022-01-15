# Change Log

The change log describes what is "Added", "Removed", "Changed" or "Fixed" between each release.

## UNRELEASED

## 1.3.0

* Support for PHP 8.1
* Drop support for PHP < 7.4
* Allow psr/cache: ^1.0 || ^2.0

## 1.2.0

### Added

* Support for PHP 8

## 1.1.0

### Added

* Added adapter-common version v1.1.0

## 1.0.0

* No changes since 0.2.0

## 0.2.0

### Added

* Support for `TaggableCacheItemPoolInterface`
* Added `EncryptedCachePool::invalidateTags()` and `EncryptedCachePool::invalidateTag()`
* Added `EncryptedItemDecorator::getCacheItem()`

### Changed

* EncryptedCachePool constructor takes a `TaggableCacheItemPoolInterface` instead of a `CacheItemPoolInterface`
* EncryptedItemDecorator constructor takes a `TaggableCacheItemInterface` instead of a `CacheItemInterface`

### Removed

* `EncryptedItemDecorator::getExpirationTimestamp()`.
* `EncryptedItemDecorator::getTags()`. Use `EncryptedItemDecorator::getPreviousTags()`
* `EncryptedItemDecorator::addTag()`. Use `EncryptedItemDecorator::setTags()`

## 0.1.0

* First release
