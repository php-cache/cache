# Change Log

The change log describes what is "Added", "Removed", "Changed" or "Fixed" between each release.

## 3.0.0

### Added

* Add `appendListItemWithExpiration()` as a protected hook for adapters that store tag lifetimes.
* Expose tag generation validation to adapters with optimized bulk reads.

### Changed

* Store a generation snapshot with each tagged item and reject stale snapshots after tag invalidation.
* Store tag metadata under portable, SHA-256-based `tag!` and `tagv!` keys. Reserve those public key prefixes.
* Require adapters to store tag payloads as `[tag, generation]` pairs instead of tag-name maps.
* Change the tag storage format. Clear shared caches before upgrading, rolling back, or running mixed versions.

### Fixed

* Preserve numeric string tag names and remove each tag-index entry once.
* Validate every tag before bulk invalidation changes cache data or metadata.
* Commit deferred tagged items before invalidation scans their tag indexes.

## 2.0.3

### Fixed

* Treat serialized payloads that reference unavailable PHP classes as cache misses.
* Identify lazy storage failures as `getItem()` operations instead of anonymous closures.
* Allow installing with `psr/simple-cache` 2 or 3.

## 2.0.0

### Changed

* Require PHP 8.2 or later.
* Require `psr/cache` 3, `psr/simple-cache` 3, and `psr/log` 3.
* Add native PHP types throughout the shared item and pool implementations.
* Require tag-list mutation hooks and direct storage writers to return success status.
* Reject malformed cache payloads instead of trusting their stored shape.

### Fixed

* Validate bulk keys before deleting data or tag indexes.
* Attempt every `setMultiple()` write and commit accepted deferred items even when another item fails.
* Report tag-index and deferred-commit failures from save, delete, and invalidation operations.

## 1.3.0

* Support for PHP 8.1
* Drop support for PHP < 7.4
* Allow psr/cache: ^1.0 || ^2.0

## 1.2.0

### Added

* Support for PHP 8

## 1.1.0

### Added

- Support for storing binary data

### Fixed

- Issue with one character variables

### Changed

- Tests are now extending `PHPUnit\Framework\TestCase`

## 1.0.0

* No changes since 0.4.0.

## 0.4.0

### Added

* `AbstractCachePool` has 4 new abstract methods: `getList`, `removeList`, `appendListItem` and `removeListItem`.
* `AbstractCachePool::invalidateTags` and `AbstractCachePool::invalidateTags`
* Added interfaces for our items and pools `PhpCachePool` and `PhpCacheItem`
* Trait to help adapters to support tags. `TagSupportWithArray`.

### Changed

* First parameter to `AbstractCachePool::storeItemInCache` must be a `PhpCacheItem`.
* Return value from `AbstractCachePool::fetchObjectFromCache` must be a an array with 4 values. Added expiration timestamp.
* `HasExpirationDateInterface` is replaced by `HasExpirationTimestampInterface`
* We do not work with `\DateTime` internally anymore. We work with timestamps.

## 0.3.3

### Fixed

* Bugfix when you fetch data from the cache storage that was saved as "non-tagging item" but fetch as a tagging item.

## 0.3.2

### Added

* Cache pools do implement `LoggerAwareInterface`

## 0.3.0

### Changed

* The `AbstractCachePool` does not longer implement `TaggablePoolInterface`. However, the `CacheItem` does still implement `TaggableItemInterface`.
* `CacheItem::getKeyFromTaggedKey` has been removed
* The `CacheItem`'s second parameter is a callable that must return an array with 3 elements; [`hasValue`, `value`, `tags`].

## 0.2.0

* No changelog before this version
