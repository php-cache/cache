# Changelog

Each release groups changes under Added, Removed, Changed, or Fixed headings.

## 2.0.3

### Fixed

* Preserve numeric string tag names when mapping tags back to the public namespace.

## 2.0.0

### Changed

* Require PHP 8.2 or later.
* Require `psr/cache` 3.
* Add native parameter and return types required by the PSR interfaces.
* Store public hierarchy keys under a distinct internal path. Clear existing hierarchy entries before upgrading.

### Added

* Accept any PSR-6 pool. Generic pools use generation keys to clear one namespace without clearing unrelated data.
* Add `NamespacedCachePool::create()`. It preserves native tag support when the wrapped pool is taggable.

### Fixed

* Encode namespace components with a reversible format that uses only PSR-6's portable key alphabet.
* Encode structural separators and literal encoding markers in public key components without changing other backend-supported bytes.
* Scope tag indexes to their namespace while preserving public tag names on cache items.
* Persist fresh generation metadata so eviction cannot expose values hidden by an earlier clear.
* Preserve hierarchy root and descendant deletion semantics on generic PSR-6 pools.
* Reject empty namespaces before they can target a hierarchical pool root.
* Preserve nested namespace storage keys without aliasing outer hierarchy keys.

## 1.2.0

* Support for PHP 8.1
* Drop support for PHP < 7.4
* Allow psr/cache: ^1.0 || ^2.0

## 1.1.0

### Added

* Support for PHP 8

## 1.0.0

### Added

* NamespacedCachePool implements HierarchicalPoolInterface

## 0.1.3

### Changed

* Updated dependencies

## 0.1.2

### Fixed

* Typos, documentation and general package improvements.

## 0.1.1

### Changed

* Updated type hints for the cache pool.
* Using `cache/hierarchical-cache:^0.3`

## 0.1.0

* First release
