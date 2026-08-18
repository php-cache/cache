# Namespaced PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/namespaced-cache/v/stable)](https://packagist.org/packages/cache/namespaced-cache)
[![Coverage](https://codecov.io/gh/php-cache/namespaced-cache/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/namespaced-cache)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package limits a PSR-6 pool to one namespace. Calling `clear()` invalidates that namespace without clearing unrelated data.

## Installation

```bash
composer require cache/namespaced-cache:^2.0
```

## Usage

```php
use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Namespaced\NamespacedCachePool;

$sharedPool = new ArrayCachePool();
$pool = NamespacedCachePool::create($sharedPool, 'billing');
```

Namespaces must contain at least one character. The constructor and `create()` reject an empty namespace.

`create()` returns a taggable decorator when the wrapped pool supports native tags. Its cache items keep `setTags()`. Tag indexes are scoped to the namespace, while `getPreviousTags()` continues to return the public tag names.

A hierarchical pool handles namespace invalidation through its native hierarchy. Other PSR-6 pools use generation keys, so old values remain stored until the backend evicts them.

The decorator persists a random generation before deriving storage keys. If the backend loses that metadata, the decorator stores a new generation instead of reusing an earlier key.

Generation records are typed. If an unrelated value occupies the first internal metadata key, the decorator probes alternate keys instead of overwriting it.

This prevents a cleared value from becoming visible after generation metadata expires or gets evicted. The decorator throws `CacheException` if it cannot persist new metadata.

Keys that start with `|` keep hierarchical deletion on every wrapped pool. Deleting `|parent` invalidates that key and its descendants without changing sibling or ordinary keys.

Deleting the hierarchy root `|` invalidates every hierarchical key in the namespace. Ordinary keys that do not start with `|` remain available.

Both `deleteItem()` and `deleteItems()` apply these hierarchy rules.

The public constructor remains available. It always returns the basic `NamespacedCachePool` type, so use `create()` when you need tag support.

Version 2 encodes namespace components with a reversible `_xHH_` byte format. ASCII letters, digits, `_`, and `.` remain unchanged. Every other byte is encoded, including each byte in non-ASCII text. A literal lowercase `_x` sequence is also encoded so it cannot look like an encoded byte.

Public key components keep ordinary bytes accepted by the wrapped pool, including `-`, `%`, and non-ASCII text. Only the structural `|` and `!` bytes and a literal lowercase `_x` sequence are encoded.

Before upgrading or rolling back, clear affected namespaces when a namespace contains bytes outside `[A-Za-z0-9_.]` or a lowercase `_x` sequence, or when a public key contains `|`, `!`, or a lowercase `_x` sequence.

Version 2 also scopes internal tag indexes to each namespace. Clear existing namespaced caches that contain tagged items before deploying this version or rolling back from it.

Version 2 preserves ordinary items in nested namespaces. Public hierarchy keys now use a separate storage path to prevent collisions with those items.

Clear existing namespaced caches that contain hierarchy keys before upgrading or rolling back. Pre-version-4 hierarchy entries use the same storage path as some nested items.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
