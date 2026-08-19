# Change Log

The change log describes what is "Added", "Removed", "Changed" or "Fixed" between each release.

## 1.0.3

### Fixed

* Treat serialized payloads that reference unavailable PHP classes as cache misses.
* Allow installing with `psr/simple-cache` 2 or 3.

## 1.0.0

### Changed

* Require PHP 8.2 or later.
* Support Illuminate Cache 11 through 13.
* Require `psr/cache` 3 and `psr/simple-cache` 3.
* Add native parameter and return types required by the PSR interfaces.

## 0.4.0

* Support for PHP 8.1
* Drop support for PHP < 7.4
* Allow psr/cache: ^1.0 || ^2.0

## 0.3.0

### Added

* Hierarchical implementation
* Support for PHP 8

## 0.2.0

### Fixed

* Let composer install laravel 5.4, 5.5 and 5.6

## 0.1.0

* First version
