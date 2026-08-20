# Memcache PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/memcache-adapter/v/stable)](https://packagist.org/packages/cache/memcache-adapter)
[![Coverage](https://codecov.io/gh/php-cache/memcache-adapter/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/memcache-adapter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package provides PSR-6 and PSR-16 cache implementations backed by the Memcache extension. The pool also supports tags.

## Installation

Install the package and enable the Memcache extension:

```bash
composer require cache/memcache-adapter:^3.0
```

## Usage

```php
use Cache\Adapter\Memcache\MemcacheCachePool;

$client = new Memcache();
$client->connect('127.0.0.1', 11211);

$pool = new MemcacheCachePool($client);
```

## Upgrading to version 3

Version 3 stores a generation snapshot with each tagged item and a separate marker for each tag. Version 2 workers cannot safely share this format.

Stop or drain all workers, clear Memcache, and then deploy version 3. Follow the same sequence before a rollback.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
