# Taggable PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/taggable-cache/v/stable)](https://packagist.org/packages/cache/taggable-cache)
[![Coverage](https://codecov.io/gh/php-cache/taggable-cache/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/taggable-cache)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package adds tag support to any PSR-6 cache pool. Native PHP Cache adapters already support tags and do not need this wrapper.

## Installation

```bash
composer require cache/taggable-cache:^3.0
```

## Usage

```php
use Cache\Taggable\TaggablePSR6PoolAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

$pool = TaggablePSR6PoolAdapter::makeTaggable(new ArrayAdapter());

$item = $pool->getItem('product.42');
$item->set(['name' => 'Desk'])->setTags(['products']);
$pool->save($item);

$pool->invalidateTag('products');
```

Install `symfony/cache` if you want to run this example. You can pass any PSR-6 pool to `makeTaggable()`.

By default, tag metadata shares the wrapped pool. Pass a second PSR-6 pool when tag metadata needs separate storage. The adapter clears the entire tag-store pool when `clear()` runs, so do not share that pool with unrelated data.

Shared-pool mode reserves public cache keys beginning with `__tag.` or the prefix returned by `getTagKeyPrefix()`. Use a separate tag-store pool if applications need that key namespace.

Each tagged item stores a snapshot of its tag generations. Invalidating a tag deletes its current generation. Items with missing or changed generations become cache misses.

The adapter does not scan or delete item bodies during invalidation. The wrapped pool removes those stale bodies through their normal expiration or eviction.

Generation markers expire at `2038-01-19 03:14:07 UTC`, the highest signed 32-bit Unix timestamp. This overrides a shorter tag store default without overflowing common backend expiration fields. Each known tag uses one small metadata item. The marker remains until invalidation, pool clearing, eviction, or that fixed bound. Marker keys use a SHA-256 digest and stay within the portable PSR-6 key alphabet and 64-character limit.

PSR-6 has no portable way to require permanent storage. When the fixed expiration passes, related items fail closed as cache misses.

If the tag store evicts a generation marker, related items become misses. Use a separate, persistent pool when metadata eviction would cause too many misses.

Two first saves can race while creating a missing generation. Both saves may return `true`, but the item with the losing generation becomes a miss. This fails closed: the race cannot expose a stale item.

Generation validation is part of the item lookup. Once `isHit()` or `get()` resolves a returned item, its hit state and value stay stable even if another process invalidates a tag.

Changing tags on an item that was already a miss does not restore its old stored body. Call `set()` before saving when that item should contain a new value.

`saveDeferred()` may persist immediately, as PSR-6 permits. This keeps the cached item and its tag metadata synchronized even if another caller commits the wrapped pool.

## Custom generation storage

Extend `TaggablePSR6PoolAdapter` when your tag store has native generation operations. The adapter's constructor and generation methods are protected. `makeTaggable()` uses late static binding.

Override `readTagGeneration()`, `writeTagGeneration()`, and `deleteTagGeneration()`. Override `getTagKeyPrefix()` when a shared tag store needs a different metadata namespace. The prefix must contain 1 to 32 letters, digits, underscores, or periods.

Use the protected `readonly` properties `$cachePool` and `$tagStorePool` to access the supplied PSR-6 pools.

`readTagGeneration()` returns the generation string, `null` for a missing marker, or `false` for a failed read. Mutation methods return `false` on failure.

Keep generation values stable until invalidation. A missing generation must remain a miss. Reusing an old generation can make invalidated values visible again.

`makeTaggable()` returns an already-taggable cache pool unchanged when no separate tag store is supplied. Pass a separate tag store when the subclass hooks must run.

## Upgrading from 2.x

Version 3 stores generation snapshots instead of reverse tag indexes. Clear wrapped and tag-store caches during the upgrade. Old tagged payloads fail closed as cache misses.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
