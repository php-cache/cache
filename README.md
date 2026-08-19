# PHP Cache

[![CI](https://github.com/php-cache/cache/actions/workflows/ci.yml/badge.svg)](https://github.com/php-cache/cache/actions/workflows/ci.yml)
[![Static analysis](https://github.com/php-cache/cache/actions/workflows/static.yml/badge.svg)](https://github.com/php-cache/cache/actions/workflows/static.yml)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

PHP Cache provides PSR-6 and PSR-16 cache adapters, decorators, and integration tools. This repository contains the packages in the [PHP Cache organization](https://github.com/php-cache).

## Requirements

Version 2 requires PHP 8.2 or later and `psr/cache` 3.x. Packages that implement PSR-16 support `psr/simple-cache` 2.x and 3.x.

Some adapters also need a PHP extension or client library. Composer lists those requirements for each split package.

## Installation

Install the package for the backend you use:

```bash
composer require cache/redis-adapter:^2.0
```

You can install every adapter and library through the aggregate package:

```bash
composer require cache/cache:^2.0
```

See the [shared documentation](https://www.php-cache.com/en/latest/) for adapter features and examples.

## Upgrading to version 2

Version 2 removes the APC adapter. Use [`cache/apcu-adapter`](https://packagist.org/packages/cache/apcu-adapter) instead.

APCu entries now use native arrays. Redis and Predis tag indexes now use sets instead of lists. Older workers cannot safely share these stores with version 2 workers.

`NamespacedCachePool` now encodes namespace bytes outside `[A-Za-z0-9_.]` and literal lowercase `_x` sequences. Public keys only encode structural `|` and `!` bytes and literal lowercase `_x` sequences. Clear affected namespaced caches before an upgrade or rollback when these transformed values are present.

Custom adapters extending `AbstractCachePool` must add the PHP 8 and PSR-3 method signatures used by version 2. The Filesystem adapter now supports Flysystem 2 and 3, the Illuminate adapter supports Illuminate 11 through 13, and the Predis adapter supports Predis 2 and 3. Flysystem 1 and older client releases are no longer supported.

Use this sequence for an upgrade or rollback:

1. Stop or drain every worker that uses the affected cache.
2. Clear each affected APCu, Redis, Predis, or namespaced cache store.
3. Deploy the target version and restart the workers.

## Contributing

Read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request. Report cache package issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
