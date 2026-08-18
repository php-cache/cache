# PSR-6 Doctrine bridge

[![Latest Stable Version](https://poser.pugx.org/cache/psr-6-doctrine-bridge/v/stable)](https://packagist.org/packages/cache/psr-6-doctrine-bridge)
[![Coverage](https://codecov.io/gh/php-cache/doctrine-bridge/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/doctrine-bridge)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package adapts a PSR-6 cache pool to the legacy `Doctrine\Common\Cache\Cache` API.

## Installation

```bash
composer require cache/psr-6-doctrine-bridge:^4.0
```

## Usage

```php
use Cache\Bridge\Doctrine\DoctrineCacheBridge;

$cacheProvider = new DoctrineCacheBridge($pool);

$cacheProvider->contains($key);
$cacheProvider->fetch($key);
$cacheProvider->save($key, $value, $ttl);
$cacheProvider->delete($key);

$cacheProvider->getCachePool();
```

`$pool` must implement `Psr\Cache\CacheItemPoolInterface` from `psr/cache` 3.x.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
