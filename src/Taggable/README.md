# Taggable PSR-6 Cache pool 
[![Gitter](https://badges.gitter.im/php-cache/cache.svg)](https://gitter.im/php-cache/cache?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)
[![Latest Stable Version](https://poser.pugx.org/cache/taggable-cache/v/stable)](https://packagist.org/packages/cache/taggable-cache)
[![codecov.io](https://codecov.io/github/php-cache/taggable-cache/coverage.svg?branch=master)](https://codecov.io/github/php-cache/taggable-cache?branch=master)
[![Total Downloads](https://poser.pugx.org/cache/taggable-cache/downloads)](https://packagist.org/packages/cache/taggable-cache)
[![Monthly Downloads](https://poser.pugx.org/cache/taggable-cache/d/monthly.png)](https://packagist.org/packages/cache/taggable-cache)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This repository has traits and interfaces that makes a PSR-6 cache implementation taggable. Using tags allow you 
to tag related items, and then clear the cached data for that tag only. It is a part of the PHP Cache organisation. To read about 
features like tagging and hierarchy support please read the shared documentation at [www.php-cache.com](http://www.php-cache.com). 

*Note: Performance will be best with a driver such as memcached or redis, which automatically purges stale records.*


### Install

```bash
composer require cache/taggable-cache
```

### Use

Read the [documentation on usage](http://www.php-cache.com/en/latest/tagging/).

### Implement

Read the [documentation on implementation](http://www.php-cache.com/en/latest/implementing-cache-pools/tagging/).

### Contribute

Contributions are very welcome! Send a pull request to the [main repository](https://github.com/php-cache/cache) or 
report any issues you find on the [issue tracker](http://issues.php-cache.com).
