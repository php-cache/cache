# Void PSR-6 Cache pool
[![Latest Stable Version](https://poser.pugx.org/cache/void-adapter/v/stable)](https://packagist.org/packages/cache/void-adapter) [![codecov.io](https://codecov.io/github/php-cache/void-adapter/coverage.svg?branch=master)](https://codecov.io/github/php-cache/void-adapter?branch=master) [![Build Status](https://travis-ci.org/php-cache/void-adapter.svg?branch=master)](https://travis-ci.org/php-cache/void-adapter) [![Total Downloads](https://poser.pugx.org/cache/void-adapter/downloads)](https://packagist.org/packages/cache/void-adapter)  [![Monthly Downloads](https://poser.pugx.org/cache/void-adapter/d/monthly.png)](https://packagist.org/packages/cache/void-adapter) [![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)

This is a void implementation of a PSR-6 cache. Other names for this adapter could be Blackhole or Null. This adapter does not save anything and will always return an empty CacheItem. It is a part of the PHP Cache organization. To read about features like tagging and hierarchy support please read the shared documentation at [www.php-cache.com](www.php-cache.com.

### Install

```bash
composer require cache/void-adapter
```

### Configure

You do not need to do any configuration to use the `VoidCachePool`.

### Usage

```php
use Cache\Adapter\Void\VoidCachePool;

$pool = new VoidCachePool();
```

### Contribute

Contributions are very welcome! Send us a pull request or report any issues you find on the [issue tracker](http://issues.php-cache.com).
