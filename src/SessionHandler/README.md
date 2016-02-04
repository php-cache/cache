# PSR-6 Session handler
[![Gitter](https://badges.gitter.im/php-cache/cache.svg)](https://gitter.im/php-cache/cache?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)
[![Latest Stable Version](https://poser.pugx.org/cache/session-handler/v/stable)](https://packagist.org/packages/cache/session-handler)
[![Total Downloads](https://poser.pugx.org/cache/session-handler/downloads)](https://packagist.org/packages/cache/session-handler)
[![Monthly Downloads](https://poser.pugx.org/cache/session-handler/d/monthly.png)](https://packagist.org/packages/cache/session-handler)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This is a PHP session handler that supports PSR-6 cache. It is a part of the PHP Cache organisation. Find more 
documentation at [www.php-cache.com](http://www.php-cache.com). 


### Install

```bash
composer require cache/session-handler
```

### Use

```php
$pool = new ArrayCachePool();
$config = ['ttl'=>3600, 'prefix'=>'foobar'];

$sessionHandler = new Psr6SessionHandler($pool, $config);
```

### Contribute

Contributions are very welcome! Send a pull request to the [main repository](https://github.com/php-cache/cache) or 
report any issues you find on the [issue tracker](http://issues.php-cache.com).