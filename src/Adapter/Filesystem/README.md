# Filesystem PSR-6 Cache pool
[![Latest Stable Version](https://poser.pugx.org/cache/filesystem-adapter/v/stable)](https://packagist.org/packages/cache/filesystem-adapter) [![codecov.io](https://codecov.io/github/php-cache/filesystem-adapter/coverage.svg?branch=master)](https://codecov.io/github/php-cache/filesystem-adapter?branch=master) [![Build Status](https://travis-ci.org/php-cache/filesystem-adapter.svg?branch=master)](https://travis-ci.org/php-cache/filesystem-adapter) [![Total Downloads](https://poser.pugx.org/cache/filesystem-adapter/downloads)](https://packagist.org/packages/cache/filesystem-adapter)  [![Monthly Downloads](https://poser.pugx.org/cache/filesystem-adapter/d/monthly.png)](https://packagist.org/packages/cache/filesystem-adapter) [![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This is a PSR-6 cache implementation for Filesystem. It is a part of the PHP Cache organization. To read about 
features like tagging and hierarchy support please read the shared documentation at [www.php-cache.com](http://www.php-cache.com). 

This implementation is using the excellent [Flysystem](http://flysystem.thephpleague.com/).

### Install

```bash
composer require cache/filesystem-adapter
```

### Configure

To create an instance of `FilesystemCachePool` you need to configure a `Filesystem` and its adapter. 

```php
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;

$filesystemAdapter = new Local(__DIR__.'/');
$filesystem        = new Filesystem($filesystemAdapter);

$pool = new FilesystemCachePool($filesystem);
```
