# MongoDB PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/mongodb-adapter/v/stable)](https://packagist.org/packages/cache/mongodb-adapter)
[![Coverage](https://codecov.io/gh/php-cache/mongodb-adapter/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/mongodb-adapter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package provides PSR-6 and PSR-16 cache implementations backed by MongoDB. The pool also supports tags.

## Installation

Install the package and enable the MongoDB extension:

```bash
composer require cache/mongodb-adapter:^2.0
```

## Usage

```php
use Cache\Adapter\MongoDB\MongoDBCachePool;
use MongoDB\Driver\Manager;

$manager = new Manager('mongodb://127.0.0.1:27017');
$collection = MongoDBCachePool::createCollection($manager, 'application', 'cache');
$pool = new MongoDBCachePool($collection);
```

`createCollection()` creates the TTL index used to remove expired entries.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
