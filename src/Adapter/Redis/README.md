# Redis PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/redis-adapter/v/stable)](https://packagist.org/packages/cache/redis-adapter)
[![Coverage](https://codecov.io/gh/php-cache/redis-adapter/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/redis-adapter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package provides a PSR-6 cache pool for `Redis`, `RedisArray`, and `RedisCluster` clients from [PhpRedis](https://github.com/phpredis/phpredis).

Use the [Predis adapter](https://github.com/php-cache/predis-adapter) when your application uses Predis.

## Installation

Install the package and enable the Redis extension:

```bash
composer require cache/redis-adapter:^2.0
```

## Usage

```php
use Cache\Adapter\Redis\RedisCachePool;

$client = new Redis();
$client->connect('127.0.0.1', 6379);
$pool = new RedisCachePool($client);
```

The pool also accepts `RedisArray` and `RedisCluster` clients:

```php
$array = new RedisArray(['127.0.0.1:6379', '127.0.0.2:6379']);
$arrayPool = new RedisCachePool($array);

$cluster = new RedisCluster(null, [
    '127.0.0.1:7000',
    '127.0.0.1:7001',
    '127.0.0.1:7002',
]);
$clusterPool = new RedisCachePool($cluster);
```

## Upgrading to version 2

Version 2 stores tag indexes as Redis sets. Older releases use Redis lists for the same keys.

Stop all workers, clear the Redis cache, and then deploy version 2. Follow the same sequence before a rollback.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
