# Change Log

The change log describes what is "Added", "Removed", "Changed" or "Fixed" between each release.

## 2.0.3

### Fixed

* Treat serialized payloads that reference unavailable PHP classes as cache misses.
* Allow installing with `psr/simple-cache` 2 or 3.

## 2.0.0

### Changed

* Require PHP 8.2 or later.
* Support Predis 2 and 3.
* Require `psr/cache` 3 and `psr/simple-cache` 3.
* Add native parameter and return types required by the PSR interfaces.
* Store tag indexes as Redis sets instead of lists. Clear the cache before upgrading or rolling back.

## 1.2.0

* Support for PHP 8.1
* Drop support for PHP < 7.4
* Allow psr/cache: ^1.0 || ^2.0

## 1.1.0

### Added

* Support for PHP 8

## 1.0.0

### Fixed

* Fixed `$path` variable not initialized in `clearOneObjectFromCache`.

## 0.5.0

### Added

* Support for the new `TaggableCacheItemPoolInterface`.
* Support for PSR-16 SimpleCache

### Changed

* The behavior of `CacheItem::getTags()` has changed. It will not return the tags stored in the cache storage.

### Removed

* `CacheItem::getExpirationDate()`. Use `CacheItem::getExpirationTimestamp()`
* `CacheItem::getTags()`. Use `CacheItem::getPreviousTags()`
* `CacheItem::addTag()`. Use `CacheItem::setTags()`

## 0.4.2

### Changed

* Rely on `Predis\ClientInterface` instead of `Predis\Client` in `PredisCachePool`

## 0.4.1

### Changed

* The `PredisCachePool::$cache` is now protected instead of private
* Using `cache/hierarchical-cache:^0.3`

## 0.4.0

* No changelog before this version
