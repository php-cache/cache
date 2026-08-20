# Change Log

The change log describes what is "Added", "Removed", "Changed" or "Fixed" between each release.

## 3.0.0

### Changed

* Require `cache/adapter-common` 3.
* Store tag generations in item documents and separate marker documents. Clear the cache collection before upgrading or rolling back.

## 2.0.3

### Fixed

* Treat serialized payloads that reference unavailable PHP classes as cache misses.
* Allow installing with `psr/simple-cache` 2 or 3.

## 2.0.0

### Changed

* Require PHP 8.2 or later.
* Require MongoDB library 2.
* Require `psr/cache` 3 and `psr/simple-cache` 3.
* Add native parameter and return types required by the PSR interfaces.

## 1.3.0

* Support for PHP 8.1
* Drop support for PHP < 7.4
* Allow psr/cache: ^1.0 || ^2.0

## 1.2.0

### Added

* Support for PHP 8

## 1.1.0

### Changed
* Upgraded version of adapter-common to 1.1.0

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

## 0.2.0

* No changelog before this version
