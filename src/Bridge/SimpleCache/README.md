# PSR-6 to PSR-16 bridge

[![Latest Stable Version](https://poser.pugx.org/cache/simple-cache-bridge/v/stable)](https://packagist.org/packages/cache/simple-cache-bridge)
[![Coverage](https://codecov.io/gh/php-cache/simple-cache-bridge/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/simple-cache-bridge)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package exposes an existing PSR-6 pool through the PSR-16 `CacheInterface`.

## Installation

```bash
composer require cache/simple-cache-bridge:^2.0
```

## Usage

```php
use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;

$pool = new ArrayCachePool();
$cache = new SimpleCacheBridge($pool);
```

Version 2 implements `psr/cache` 3 and supports `psr/simple-cache` 2 and 3.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
