# Change Log

The change log describes what is "Added", "Removed", "Changed" or "Fixed" between each release.

## 4.0.4

### Changed

* Test against PHP Cache 3 adapters.

## 4.0.3

### Fixed

* Retry rejected legacy keys as portable SHA-256 keys while preserving accepted key mappings.

## 4.0.0

### Changed

* Require PHP 8.2 or later.
* Require Doctrine Cache 2.2 and a PSR-6 implementation compatible with `psr/cache` 3.
* Add native parameter and return types required by Doctrine Cache 2.2.

## 3.2.0

* Support for PHP 8.1
* Drop support for PHP < 7.4
* Allow psr/cache: ^1.0 || ^2.0

## 3.1.0

### Added

* Support for PHP 8

### Fixed

* Return result from `Doctrine::clear()`

## 3.0.1

### Changed

* Bumped versions on dependencies.

## 3.0.0

### Changed

* Changed Namespace from `Cache\Bridge` to `Cache\Bridge\Doctrine`

## 2.2.0

* No changelog before this version
