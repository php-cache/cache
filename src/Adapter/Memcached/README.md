# Memcached PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/memcached-adapter/v/stable)](https://packagist.org/packages/cache/memcached-adapter)
[![Coverage](https://codecov.io/gh/php-cache/memcached-adapter/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/memcached-adapter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package provides PSR-6 and PSR-16 cache implementations backed by the Memcached extension. The pool supports tags and hierarchical keys.

## Installation

Install the package and enable the Memcached extension:

```bash
composer require cache/memcached-adapter:^3.0
```

## Usage

```php
use Cache\Adapter\Memcached\MemcachedCachePool;

$client = new Memcached();
$client->addServer('127.0.0.1', 11211);

$pool = new MemcachedCachePool($client);
```

The pool enables Memcached's binary protocol by default. Pass a Memcached option map as the second constructor argument to override it or set other client options:

```php
$pool = new MemcachedCachePool($client, [
    Memcached::OPT_BINARY_PROTOCOL => false,
    Memcached::OPT_CONNECT_TIMEOUT => 1000,
]);
```

## Bulk operations

The PSR-16 `getMultiple()`, `setMultiple()`, and `deleteMultiple()` methods use the native Memcached bulk commands. Bulk writes keep the same expiration for every value and remove old tag references after storage succeeds.

The pool treats `false` from Memcached's `getMulti()` as a backend failure. It throws `CachePoolException` instead of returning a batch of cache misses.

## Upgrading to version 3

Version 3 stores a generation snapshot with each tagged item and a separate marker for each tag. Version 2 workers cannot safely share this format.

The constructor now accepts a Memcached option map instead of a Boolean binary-protocol flag.

Stop or drain all workers, clear Memcached, and then deploy version 3. Follow the same sequence before a rollback.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
