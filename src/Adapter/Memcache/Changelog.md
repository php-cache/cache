# Change Log

The change log describes what is "Added", "Removed", "Changed" or "Fixed" between each release.

## 2.0.3

### Fixed

* Treat serialized payloads that reference unavailable PHP classes as cache misses.
* Allow installing with `psr/simple-cache` 2 or 3.

## 2.0.0

### Changed

* Require PHP 8.2 or later.
* Require `psr/cache` 3 and `psr/simple-cache` 3.
* Add native parameter and return types required by the PSR interfaces.

## 1.2.0

* Support for PHP 8.1
* Drop support for PHP < 7.4
* Allow psr/cache: ^1.0 || ^2.0

## 1.1.0

### Added

* Support for PHP 8

## 1.0.0

* No changes since 0.3.0

## 0.3.0

### Added

* Support for the new `TaggableCacheItemPoolInterface`.
* Support for PSR-16 SimpleCache

### Changed

* The behavior of `CacheItem::getTags()` has changed. It will not return the tags stored in the cache storage.

### Removed

* `CacheItem::getExpirationDate()`. Use `CacheItem::getExpirationTimestamp()`
* `CacheItem::getTags()`. Use `CacheItem::getPreviousTags()`
* `CacheItem::addTag()`. Use `CacheItem::setTags()`

## 0.2.2

### Added

* Support for PHP7

### Changed

* The `MemcachedCachePool::$cache` is now protected instead of private

## 0.2.1

* No changelog before this version
