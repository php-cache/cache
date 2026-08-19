# Changelog

Each release groups changes under Added, Removed, Changed, or Fixed headings.

## 2.0.4

### Fixed

* Allow installing with `psr/simple-cache` 2 or 3.

## 2.0.3

### Fixed

* Validate keys against every chain member before returning a cached item or starting a broadcast mutation.

## 2.0.0

### Changed

* Require PHP 8.2 or later.
* Require `psr/cache` 3 and `psr/simple-cache` 3.
* Require `cache/simple-cache-bridge` 4.
* Add native parameter and return types required by the PSR interfaces.
* Implement PSR-16 directly through the PSR-6 bridge.
* Require every chain member to implement `Cache\Adapter\Common\PhpCachePool`.
* Preserve numeric-string keys returned by `getItems()`.

### Fixed

* Preserve stored tags and expiration during a backfill to earlier pools.
* Keep the highest-priority result when `getItems()` receives duplicate keys.
* Apply `skip_on_failure` consistently to eager and lazy read and backfill failures.
* Apply `skip_on_failure` to raw backend exceptions without hiding invalid cache keys.
* Throw `NoPoolAvailableException` when no chain member completes an operation.
* Reject non-transferable items before writing to any chain member.

## 1.2.0

* Support for PHP 8.1
* Drop support for PHP < 7.4
* Allow psr/cache: ^1.0 || ^2.0

## 1.1.0

### Added

* Support for PHP 8

## 1.0.0

### Added

* The `CachePoolChain` does now implement `Cache\TagInterop\TaggableCacheItemPoolInterface`

### Removed

* Removed deprecated function `clearTags`

## 0.5.1

### Fixed

* Fixed issue with generator
* Make sure `getItems` always save values back to previous pools

## 0.5.0

### Added

* Support for the new `TaggableCacheItemPoolInterface`.

## 0.4.0

### Changed

* `CachePoolChain::getPools` is protected instead of public

## 0.3.1

* No changelog before this version
