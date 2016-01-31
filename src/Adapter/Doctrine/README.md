# Doctrine PSR-6 Cache pool 
[![Latest Stable Version](https://poser.pugx.org/cache/doctrine-adapter/v/stable)](https://packagist.org/packages/cache/doctrine-adapter) [![codecov.io](https://codecov.io/github/php-cache/doctrine-adapter/coverage.svg?branch=master)](https://codecov.io/github/php-cache/doctrine-adapter?branch=master) [![Build Status](https://travis-ci.org/php-cache/doctrine-adapter.svg?branch=master)](https://travis-ci.org/php-cache/doctrine-adapter) [![Total Downloads](https://poser.pugx.org/cache/doctrine-adapter/downloads)](https://packagist.org/packages/cache/doctrine-adapter)  [![Monthly Downloads](https://poser.pugx.org/cache/doctrine-adapter/d/monthly.png)](https://packagist.org/packages/cache/doctrine-adapter) [![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This is a PSR-6 cache implementation using Doctrine. It is a part of the PHP Cache organisation. To read about 
features like tagging and hierarchy support please read the shared documentation at [www.php-cache.com](http://www.php-cache.com). 

This is a PSR-6 to Doctrine bridge. If you are interested in a Doctrine to PSR-6 bridge you should have a look at 
[PSR-6 Doctrine Bridge](https://github.com/php-cache/doctrine-bridge).

### Install

```bash
composer require cache/doctrine-adapter
```

## Usage

```php
use Doctrine\Common\Cache\MemcachedCache;
use Cache\Doctrine\CachePool;


$memcached = new \Memcached();
$memcached->addServer('localhost', 11211);

// Create a instance of Doctrine's MemcachedCache
$doctrineCache = new MemcachedCache();
$doctrineCache->setMemcached($memcached);

// Wrap Doctrine's cache with the PSR-6 adapter
$pool = new CachePool($doctrineCache);
```
