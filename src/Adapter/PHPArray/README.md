# Array PSR-6 Cache pool 
[![Latest Stable Version](https://poser.pugx.org/cache/array-adapter/v/stable)](https://packagist.org/packages/cache/array-adapter) [![codecov.io](https://codecov.io/github/php-cache/array-adapter/coverage.svg?branch=master)](https://codecov.io/github/php-cache/array-adapter?branch=master) [![Build Status](https://travis-ci.org/php-cache/array-adapter.svg?branch=master)](https://travis-ci.org/php-cache/array-adapter) [![Total Downloads](https://poser.pugx.org/cache/array-adapter/downloads)](https://packagist.org/packages/cache/array-adapter)  [![Monthly Downloads](https://poser.pugx.org/cache/array-adapter/d/monthly.png)](https://packagist.org/packages/cache/array-adapter) [![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This is a PSR-6 cache implementation using a PHP array. It is a part of the PHP Cache organisation. To read about 
features like tagging and hierarchy support please read the shared documentation at [www.php-cache.com](http://www.php-cache.com). 

This adapter could also be called Ephemeral or Memory adapter.  		

### Install

```bash
composer require cache/array-adapter
```

### Configure

No configuration is needed. 

```php
$pool = new ArrayCachePool();
```