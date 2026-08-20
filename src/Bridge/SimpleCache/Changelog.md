# Change Log

The change log describes what is "Added", "Removed", "Changed" or "Fixed" between each release.

## 2.0.4

### Changed

* Test against Array Adapter 3.

## 2.0.3

### Fixed

* Allow installing with `psr/simple-cache` 2 or 3.

## 2.0.0

### Changed

* Require PHP 8.2 or later.
* Require `psr/cache` 3 and `psr/simple-cache` 3.
* Add native parameter and return types required by the PSR interfaces.

### Fixed

* Preserve numeric-string keys returned by `getMultiple()`.
* Translate lazy PSR-6 invalid-key failures to PSR-16 exceptions before mutating a bulk write.
* Attempt every `setMultiple()` write and commit accepted deferred items even when another item fails.

## 1.2.0

* Support for PHP 8.1
* Drop support for PHP < 7.4
* Allow psr/cache: ^1.0 || ^2.0

## 1.1.0

### Added

* Support for PHP 8

## 1.0.0

* No changes since 0.1.1

## 0.1.1

### Fixed

* Bugs with iterators

## 0.1.0

* First release
