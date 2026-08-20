# Illuminate PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/illuminate-adapter/v/stable)](https://packagist.org/packages/cache/illuminate-adapter)
[![Coverage](https://codecov.io/gh/php-cache/illuminate-adapter/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/illuminate-adapter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package exposes an Illuminate cache store through PSR-6 and PSR-16. The pool supports tags and hierarchical keys.

## Installation

```bash
composer require cache/illuminate-adapter:^2.0
```

Version 2 supports Illuminate 11 through 13.

## Usage

```php
use Cache\Adapter\Illuminate\IlluminateCachePool;
use Illuminate\Cache\ArrayStore;

$store = new ArrayStore();
$pool = new IlluminateCachePool($store);
```

Pass any implementation of `Illuminate\Contracts\Cache\Store` to the constructor.

## Upgrading to version 2

Version 2 stores a generation snapshot with each tagged item and a separate marker for each tag. Version 1 workers cannot safely share this format.

Stop or drain all workers, clear the backing store, and then deploy version 2. Follow the same sequence before a rollback.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
