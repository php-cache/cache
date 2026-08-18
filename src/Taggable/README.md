# Taggable PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/taggable-cache/v/stable)](https://packagist.org/packages/cache/taggable-cache)
[![Coverage](https://codecov.io/gh/php-cache/taggable-cache/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/taggable-cache)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package adds tag support to any PSR-6 cache pool. Native PHP Cache adapters already support tags and do not need this wrapper.

## Installation

```bash
composer require cache/taggable-cache:^2.0
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

By default, tag metadata shares the wrapped pool. Pass a second PSR-6 pool when tag metadata needs separate storage.

`saveDeferred()` may persist immediately, as PSR-6 permits. This keeps the cached item and its tag metadata synchronized even if another caller commits the wrapped pool.

## Native tag-list storage

Extend `TaggablePSR6PoolAdapter` when your tag store has native list operations. The constructor and list methods are protected, and `makeTaggable()` uses late static binding.

Override `appendListItem()`, `removeListItem()`, `removeList()`, and `getList()`. Keep the native client in your subclass. The adapter does not expose either wrapped pool as mutable subclass state.

The three mutation methods return `false` when the tag store cannot update its index. The adapter reports that failure from the calling cache operation.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
