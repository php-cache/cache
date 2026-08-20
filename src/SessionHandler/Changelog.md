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
* Add native parameter and return types required by the PSR interfaces and PHP session handlers.
* Require a backend-neutral `SessionLockInterface` when constructing either session handler.
* Hold an acquired session lock until `close()` or `destroy()`, including writes and timestamp updates.
* Switch locks before writing a regenerated session ID.
* Release acquired locks when cache operations throw exceptions.

### Fixed

* Return `false` from PSR-6 timestamp updates when the session no longer exists.

## 1.2.0

* Support for PHP 8.1
* Drop support for PHP < 7.4
* Allow psr/cache: ^1.0 || ^2.0

## 1.1.0

### Added

* Support for PHP 8
* New PSR-16 SessionHandler
* Implemented PHP 7.0's `SessionUpdateTimestampHandlerInterface` with a new `AbstractSessionHandler` base class

## 1.0.0

* No changes since 0.2.1

## 0.2.1

### Fixed

* Typos, documentation and general package improvements.

## 0.2.0

* No changelog before this version
