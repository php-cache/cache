# Memcached PSR-6 Cache pool 
[![Latest Stable Version](https://poser.pugx.org/cache/memcached-adapter/v/stable)](https://packagist.org/packages/cache/memcached-adapter) [![codecov.io](https://codecov.io/github/php-cache/memcached-adapter/coverage.svg?branch=master)](https://codecov.io/github/php-cache/memcached-adapter?branch=master) [![Build Status](https://travis-ci.org/php-cache/memcached-adapter.svg?branch=master)](https://travis-ci.org/php-cache/memcached-adapter) [![Total Downloads](https://poser.pugx.org/cache/memcached-adapter/downloads)](https://packagist.org/packages/cache/memcached-adapter)  [![Monthly Downloads](https://poser.pugx.org/cache/memcached-adapter/d/monthly.png)](https://packagist.org/packages/cache/memcached-adapter) [![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This is a PSR-6 cache implementation using Memcached. It is a part of the PHP Cache organisation. To read about 
features like tagging and hierarchy support please read the shared documentation at [www.php-cache.com](http://www.php-cache.com). 

### Install

```bash
composer require cache/memcached-adapter
```

### Configure

To create an instance of `MemcachedCachePool` you need to configure a `\Memcached` client. 

```php
$client = new \Memcached();
$client->addServer('localhost', 11211);
$pool = new MemcachedCachePool($client);
```
