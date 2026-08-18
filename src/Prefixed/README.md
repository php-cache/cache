# Prefixed cache decorators

[![Latest Stable Version](https://poser.pugx.org/cache/prefixed-cache/v/stable)](https://packagist.org/packages/cache/prefixed-cache)
[![Coverage](https://codecov.io/gh/php-cache/prefixed-cache/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/prefixed-cache)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package adds a fixed prefix to keys sent to an existing PSR-6 or PSR-16 cache.

Unlike `NamespacedCachePool`, clearing a prefixed cache clears the entire underlying pool. Use the namespaced package when you need isolated clearing.

## Installation

```bash
composer require cache/prefixed-cache:^2.0
```

## Usage

```php
use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Prefixed\PrefixedCachePool;

$sharedPool = new ArrayCachePool();
$pool = PrefixedCachePool::create($sharedPool, 'billing.');
```

`create()` returns a taggable decorator when the wrapped PSR-6 pool supports native tags. Its cache items keep `setTags()`, and tag invalidation uses the wrapped pool.

The public constructor remains available. It always returns the basic `PrefixedCachePool` type, so use `create()` when you need tag support.

Use `PrefixedSimpleCache` to decorate a PSR-16 cache.

Prefixes are encoded before they are joined to public keys. The reversible `_xHH_` format leaves ASCII letters, digits, `_`, and `.` unchanged. Every other byte is encoded, including each byte in non-ASCII text. A literal lowercase `_x` sequence is also encoded so it cannot look like an encoded byte.

Before upgrading to version 2 or rolling back from it, clear affected caches when a prefix contains bytes outside `[A-Za-z0-9_.]` or a lowercase `_x` sequence. Those prefixes use different storage keys in version 2.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
