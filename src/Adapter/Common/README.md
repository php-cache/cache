# Common cache adapter components

[![Latest Stable Version](https://poser.pugx.org/cache/adapter-common/v/stable)](https://packagist.org/packages/cache/adapter-common)
[![Coverage](https://codecov.io/gh/php-cache/adapter-common/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/adapter-common)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package provides the shared PSR-6 and PSR-16 implementation used by PHP Cache adapters. It also provides reusable tag support.

Application code should install a concrete adapter instead. Adapter authors can install these components directly.

## Installation

```bash
composer require cache/adapter-common:^3.0
```

Version 3 requires PHP 8.2 and `psr/cache` 3. Its PSR-16 API supports `psr/simple-cache` 2 and 3.

## Upgrading to version 3

Version 3 adds `appendListItemWithExpiration()` as a protected extension point. Its default implementation calls `appendListItem()` without changing existing tag storage.

Custom subclasses that already declare this method must use the version 3 signature and protected visibility.

Version 3 also stores a generation snapshot with each tagged item. The default implementation stores tag indexes and generations under `tag!` and `tagv!` keys that contain a SHA-256 digest of the tag. These metadata keys stay within the portable 64-character PSR-6 key alphabet. Adapter payloads must preserve the `[tag, generation]` pairs returned by `PhpCacheItem::getTagVersions()`.

Optimized read paths that bypass `getItem()` must call `tagVersionsAreCurrent()` before returning a stored value. Adapters can override `readTagVersion()`, `writeTagVersion()`, and `deleteTagVersion()` when the backend stores generation markers natively.

Public cache keys that start with `tag!` or `tagv!` are reserved for tag metadata. Applications must rename any keys that use either prefix.

Version 2 workers cannot safely read or update version 3 tag metadata. Stop or drain all workers, clear the cache, and then deploy version 3. Follow the same sequence before a rollback.

## Documentation

Read the [PHP Cache documentation](https://www.php-cache.com/) to learn about adapters, tags, and hierarchical keys.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
