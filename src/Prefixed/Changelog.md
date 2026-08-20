# Change Log

The change log describes what is "Added", "Removed", "Changed" or "Fixed" between each release.

## 2.0.4

### Changed

* Test against Array Adapter 2 and 3.

## 2.0.3

### Fixed

* Allow installing with `psr/simple-cache` 2 or 3.

## 2.0.0

### Changed

* Require PHP 8.2 or later.
* Require `psr/cache` 3 and `psr/simple-cache` 3.
* Add native parameter and return types required by the PSR interfaces.
* Remove the unused `cache/hierarchical-cache` dependency.

### Added

* Add `PrefixedCachePool::create()`. It preserves native tag support when the wrapped pool is taggable.

### Fixed

* Encode every non-portable prefix byte and literal encoding marker with a reversible format that uses only the PSR key alphabet. Prefixes that contain transformed bytes require a cache clear when upgrading or rolling back.

## 1.2.0

* Support for PHP 8.1
* Drop support for PHP < 7.4
* Allow psr/cache: ^1.0 || ^2.0

## 1.1.0

### Added

* Support for PHP 8
* New decorator PrefixedSimpleCache to allow usage of PSR-16 compatible adapters.

## 1.0.0

### Removed

* Dependency on `cache/hierarchical-cache`

## 0.1.2

### Changed

* We now support cache/hierarchical-cache: ^0.4

## 0.1.1

### Fixed

* Typos, documentation and general package improvements.
