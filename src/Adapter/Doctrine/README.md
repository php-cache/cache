# Doctrine PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/doctrine-adapter/v/stable)](https://packagist.org/packages/cache/doctrine-adapter)
[![Coverage](https://codecov.io/gh/php-cache/doctrine-adapter/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/doctrine-adapter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package adapts a legacy `Doctrine\Common\Cache\Cache` implementation to PSR-6 and PSR-16.

Use the [PSR-6 Doctrine bridge](https://github.com/php-cache/doctrine-bridge) when a legacy library needs Doctrine Cache and your application already has a PSR-6 pool.

## Installation

```bash
composer require cache/doctrine-adapter:^2.0
```

## Usage

Pass an existing Doctrine Cache implementation to the pool:

```php
use Cache\Adapter\Doctrine\DoctrineCachePool;
use Doctrine\Common\Cache\Cache;

function createPool(Cache $doctrineCache): DoctrineCachePool
{
    return new DoctrineCachePool($doctrineCache);
}
```

Doctrine Cache 2.x provides the legacy interfaces but no storage drivers. Your application must provide the concrete implementation.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
