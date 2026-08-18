# Illuminate PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/illuminate-adapter/v/stable)](https://packagist.org/packages/cache/illuminate-adapter)
[![Coverage](https://codecov.io/gh/php-cache/illuminate-adapter/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/illuminate-adapter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package exposes an Illuminate cache store through PSR-6 and PSR-16. The pool supports tags and hierarchical keys.

## Installation

```bash
composer require cache/illuminate-adapter:^1.0
```

Version 1 supports Illuminate 11 through 13.

## Usage

```php
use Cache\Adapter\Illuminate\IlluminateCachePool;
use Illuminate\Cache\ArrayStore;

$store = new ArrayStore();
$pool = new IlluminateCachePool($store);
```

Pass any implementation of `Illuminate\Contracts\Cache\Store` to the constructor.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
