# Changelog

The change log describes what is "Added", "Removed", "Changed" or "Fixed" between each release.

## 3.0.0

### Changed

* Test against Array Adapter 2 and 3.
* Expose the wrapped cache and tag-store pools as protected `readonly` properties on `TaggablePSR6PoolAdapter`.
* Store a generation snapshot with each tagged item instead of a reverse tag index.
* Validate tag generations in `getItem()`, `getItems()`, and `hasItem()`.
* Keep one generation marker for each known tag until invalidation, pool clearing, eviction, or the signed 32-bit Unix time bound.

### Fixed

* Make concurrent generation initialization fail closed instead of exposing a stale item.
* Prevent save and invalidation races from leaving a stale tagged item visible.
* Treat missing, evicted, malformed, and legacy tag generations as cache misses.
* Keep `isHit()` and `get()` stable for the lifetime of a returned item.
* Preserve the value and tags when an unchanged fetched item is saved again.
* Support numeric-string tags in generation snapshots.
* Keep metadata keys within the portable PSR-6 alphabet and length limit.
* Keep generation marker expirations within common backend integer limits.
* Validate every tag before invalidation changes metadata.
* Prevent changing tags on a cache miss from restoring its invalidated body.

### Removed

* Remove `ExtensibleTaggablePSR6PoolAdapter`. Extend `TaggablePSR6PoolAdapter` directly instead.
* Remove the protected tag-list hooks. Override the generation read, write, and delete hooks instead.

## 2.1.0

### Added

* Add `ExtensibleTaggablePSR6PoolAdapter` as an opt-in extension base with protected pool access.

## 2.0.0

### Changed

* Require PHP 8.2 or later.
* Require `psr/cache` 3.
* Add native parameter and return types required by the PSR interfaces.
* Persist `TaggablePSR6PoolAdapter::saveDeferred()` immediately so a wrapped pool cannot commit an item without its tag metadata.

### Added

* Let subclasses replace tag-list operations. The pools remain private, while the constructor and list hooks form the extension API.
* Use late static binding in `TaggablePSR6PoolAdapter::makeTaggable()`.

### Fixed

* Keep tag indexes intact when deleting or clearing the wrapped pool fails.
* Report tag-store write and delete failures to callers.

## 1.2.0

* Support for PHP 8.1
* Drop support for PHP < 7.4
* Allow psr/cache: ^1.0 || ^2.0

## 1.1.0

### Added

* Support for PHP 8

## 1.0.0

### Added

* `Cache\Taggable\Exception\InvalidArgumentException`

### Changed

* We do not throw `Cache\Adapter\Common\Exception\InvalidArgumentException` anymore. Instead we throw
`Cache\Taggable\Exception\InvalidArgumentException`. Both exceptions do implement `Psr\Cache\InvalidArgumentException`
* We do not require `cache/adapter-common`

### Removed

* Deprecated interfaces `TaggableItemInterface` and `TaggablePoolInterface`

## 0.5.1

### Fixed

* Bug on `TaggablePSR6ItemAdapter::isItemCreatedHere` where item value was `null`.

## 0.5.0

### Added

* Support for `TaggableCacheItemPoolInterface`

### Changed

* The behavior of `TaggablePSR6ItemAdapter::getTags()` has changed. It will not return the tags stored in the cache storage.

### Removed

* `TaggablePoolTrait`
* Deprecated `TaggablePoolInterface` in favor of `Cache\TagInterop\TaggableCacheItemPoolInterface`
* Deprecated `TaggableItemInterface` in favor of `Cache\TagInterop\TaggableCacheItemInterface`
* Removed support for `TaggablePoolInterface` and `TaggableItemInterface`
* `TaggablePSR6ItemAdapter::getTags()`. Use `TaggablePSR6ItemAdapter::getPreviousTags()`
* `TaggablePSR6ItemAdapter::addTag()`. Use `TaggablePSR6ItemAdapter::setTags()`

## 0.4.3

### Fixed

* Do not lose the data when you start using the `TaggablePSR6PoolAdapter`

## 0.4.2

### Changed

* Updated version for integration tests
* Made `TaggablePSR6PoolAdapter::getTags` protected instead of private

## 0.4.1

### Fixed

* Saving an expired value should be the same as removing that value

## 0.4.0

This is a big BC break. The API is rewritten and how we store tags has changed. Each tag is a key to a list in the
cache storage. The list contains keys to items that uses that tag.

* The `TaggableItemInterface` is completely rewritten. It extends `CacheItemInterface` and has three methods: `getTags`, `setTags` and `addTag`.
* The `TaggablePoolInterface` is also rewritten. It has a new `clearTags` function.
* The `TaggablePoolTrait` has new methods to manipulate the list of tags.

## 0.3.1

* No changelog before this version
