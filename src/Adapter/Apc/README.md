# Apc PSR-6 Cache pool 
[![Latest Stable Version](https://poser.pugx.org/cache/apc-adapter/v/stable)](https://packagist.org/packages/cache/apc-adapter) [![codecov.io](https://codecov.io/github/php-cache/apc-adapter/coverage.svg?branch=master)](https://codecov.io/github/php-cache/apc-adapter?branch=master) [![Build Status](https://travis-ci.org/php-cache/apc-adapter.svg?branch=master)](https://travis-ci.org/php-cache/apc-adapter) [![Total Downloads](https://poser.pugx.org/cache/apc-adapter/downloads)](https://packagist.org/packages/cache/apc-adapter)  [![Monthly Downloads](https://poser.pugx.org/cache/apc-adapter/d/monthly.png)](https://packagist.org/packages/cache/apc-adapter) [![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This is a PSR-6 cache implementation using Apc. It is a part of the PHP Cache organisation. To read about 
features like tagging and hierarchy support please read the shared documentation at [www.php-cache.com](http://www.php-cache.com). 

### Install

```bash
composer require cache/apc-adapter
```

### Configure

You do not need to do any configuration to use the `ApcCachePool`.

```php
$pool = new ApcCachePool();
```
