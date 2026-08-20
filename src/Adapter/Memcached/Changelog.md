# Changelog

Each release groups changes under Added, Removed, Changed, or Fixed headings.

## 3.0.0

### Changed

* Replace the `bool` constructor flag with a Memcached option map. The binary protocol remains enabled unless the map overrides it.
* Require `cache/adapter-common` 3 and `cache/hierarchical-cache` 2.1.
* Store tag generations in item payloads and separate marker entries. Clear Memcached before upgrading or rolling back.

### Fixed

* Reject stale tag generations during bulk `getMultiple()` reads.

## 2.1.0

### Added

* Allow callers to turn off the Memcached binary protocol through the pool constructor.

### Fixed

* Treat serialized payloads that reference unavailable PHP classes as cache misses.
* Allow installing with `psr/simple-cache` 2 or 3.

## 2.0.0

### Added

* Use native Memcached bulk commands for PSR-16 bulk reads, writes, and deletes.

### Changed

* Require PHP 8.2 or later.
* Require `psr/cache` 3 and `psr/simple-cache` 3.
* Add native parameter and return types required by the PSR interfaces.

### Fixed

* Throw `CachePoolException` when Memcached reports a failed `getMulti()` call.

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

* Issue when TTL is larger than 30 days.

## 0.3.2

### Changed

* The `MemcachedCachePool::$cache` is now protected instead of private
* Using `cache/hierarchical-cache:^0.3`

## 0.3.1

* No changelog before this version
