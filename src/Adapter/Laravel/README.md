# Laravel PSR-6 Cache pool
[![Gitter](https://badges.gitter.im/php-cache/cache.svg)](https://gitter.im/php-cache/cache?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)
[![Latest Stable Version](https://poser.pugx.org/cache/laravel-adapter/v/stable)](https://packagist.org/packages/cache/laravel-adapter)
[![codecov.io](https://codecov.io/github/php-cache/laravel-adapter/coverage.svg?branch=master)](https://codecov.io/github/php-cache/laravel-adapter?branch=master)
[![Total Downloads](https://poser.pugx.org/cache/laravel-adapter/downloads)](https://packagist.org/packages/cache/laravel-adapter)
[![Monthly Downloads](https://poser.pugx.org/cache/laravel-adapter/d/monthly.png)](https://packagist.org/packages/cache/laravel-adapter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This is a PSR-6 cache implementation using Laravel cache. It is a part of the PHP Cache organisation. To read about
features like tagging and hierarchy support please read the shared documentation at [www.php-cache.com](http://www.php-cache.com).

### Install

```bash
composer require cache/laravel-adapter
```

### Usage

```php
use Cache\Adapter\Laravel\LaravelCacheAdapter;
use Illuminate\Cache\FileStore;
use Illuminate\Filesystem\Filesystem;

$store = new FileStore(
    new Filesystem(),
    'laravel'
);

// Wrap Laravel's cache with the PSR-6 adapter
$pool = new LaravelCacheAdapter($store);

```

### Contribute

Contributions are very welcome! Send a pull request to the [main repository](https://github.com/php-cache/cache) or
report any issues you find on the [issue tracker](http://issues.php-cache.com).
