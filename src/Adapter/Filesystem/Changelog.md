# Changelog

Each release groups changes under Added, Removed, Changed, or Fixed headings.

## 3.0.0

### Changed

* Expose `getFilePath()` as a protected extension point for custom file layouts.
* Hash keys into 256 shard directories to avoid platform-specific filenames and large flat directories.
* Clear cache files recursively so custom layouts can use nested directories.
* Require `cache/adapter-common` 3.
* Store tag generations in item payloads and separate marker files. Clear the cache directory before upgrading or rolling back.

## 2.0.4

### Fixed

* Treat serialized items and tag indexes that reference unavailable PHP classes as cache misses.
* Allow installing with `psr/simple-cache` 2 or 3.

## 2.0.3

### Fixed

* Validate filesystem key constraints in `getItem()` and safely store the PSR-required `.` and `..` keys.

## 2.0.0

### Added

* Add accessors for the Flysystem instance and cache directory.

### Changed

* Require PHP 8.2 or later.
* Support Flysystem 2 and 3 through `FilesystemOperator`.
* Require `psr/cache` 3 and `psr/simple-cache` 3.
* Add native parameter and return types required by the PSR interfaces.
* Normalize leading and trailing slashes in `setFolder()`.

### Fixed

* Reject folder values that resolve to the Flysystem root or traverse through a parent directory.

## 1.2.0

* Support for PHP 8.1
* Drop support for PHP < 7.4
* Allow psr/cache: ^1.0 || ^2.0

## 1.1.0

### Added

* Support for PHP 8

### Changed

* Use `League\Flysystem\FilesystemInterface` instead of concrete `League\Flysystem\Filesystem` class

## 1.0.0

* No changes since 0.4.0

## 0.4.0

### Added

* Support for the new `TaggableCacheItemPoolInterface`.
* Support for PSR-16 SimpleCache

### Changed

* The behavior of `CacheItem::getTags()` has changed. It does not return tags stored in the cache storage.

### Removed

* `CacheItem::getExpirationDate()`. Use `CacheItem::getExpirationTimestamp()`
* `CacheItem::getTags()`. Use `CacheItem::getPreviousTags()`
* `CacheItem::addTag()`. Use `CacheItem::setTags()`

## 0.3.3

### Fixed

* Race condition in `fetchObjectFromCache`.

## 0.3.2

### Changed

* Using `Filesystem::update` instead of `Filesystem::delete` and `Filesystem::write`.

## 0.3.1

### Added

* Add ability to change cache path in FilesystemCachePool

## 0.3.0

* No changelog before this version
