# PSR-6 and PSR-16 cache pool chain

[![Latest Stable Version](https://poser.pugx.org/cache/chain-adapter/v/stable)](https://packagist.org/packages/cache/chain-adapter)
[![Coverage](https://codecov.io/gh/php-cache/chain-adapter/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/chain-adapter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package combines multiple PHP Cache pools into one pool. `CachePoolChain` implements PSR-6 and PSR-16.

Reads use the first available value. Writes update each configured pool.

## Installation

```bash
composer require cache/chain-adapter:^2.1
```

## Usage

```php
use Cache\Adapter\Apcu\ApcuCachePool;
use Cache\Adapter\Chain\CachePoolChain;
use Cache\Adapter\Redis\RedisCachePool;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$pool = new CachePoolChain([
    new ApcuCachePool(),
    new RedisCachePool($redis),
]);

$pool->set('key', 'value', 60);
$value = $pool->get('key');
```

Each pool in the chain must implement `Cache\Adapter\Common\PhpCachePool`. This keeps cache items transferable while the chain backfills earlier pools. Wrap the completed chain with other PSR-6 decorators instead of adding generic or decorated pools as chain members.

A key must be accepted by every pool in the chain. The chain checks each member before returning a higher-priority hit or starting a write or delete, which prevents partial mutations when one backend has narrower key rules.

Backfills preserve the cached value, expiration, and stored tags. Tag invalidation therefore removes copies from every tier.

## Fallback behavior

By default, the chain throws exceptions from a pool. Set `skip_on_failure` to remove the failed pool and continue the operation.

Install the optional no-op pool when failures must become cache misses:

```bash
composer require cache/void-adapter:^3.0
```

```php
use Cache\Adapter\Chain\CachePoolChain;
use Cache\Adapter\Void\VoidCachePool;

$pool = new CachePoolChain(
    [$redisPool, new VoidCachePool()],
    ['skip_on_failure' => true],
);
```

The chain removes the failed pool for the life of that `CachePoolChain` instance. A configured logger receives a warning with the exception.

Raw exceptions from backend operations use the same fallback. Invalid cache keys always throw and never remove a pool.

If every member throws before one completes the operation, the chain throws `NoPoolAvailableException`. `skip_on_failure` does not convert a fully unavailable chain into a miss or `false` result.

Add `VoidCachePool` last when cache failures must become misses. Writes still run against every active pool, including `VoidCachePool`.

The chain cannot catch an exception thrown before the pool reaches the chain constructor. Delay backend connections until a cache operation when possible.

Version 2.1 supports `cache/adapter-common` 2 and 3. Follow each member adapter's upgrade steps before mixing major versions in a chain.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
