# Cache utilities

[![Latest Stable Version](https://poser.pugx.org/cache/util/v/stable)](https://packagist.org/packages/cache/util)
[![Coverage](https://codecov.io/gh/php-cache/util/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/util)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package provides small utilities for PSR cache implementations.

## Installation

```bash
composer require cache/util:^1.0
```

## Usage

The `remember()` helper reads a PSR-16 value or creates and stores it when the cache misses.

```php
use function Cache\Util\SimpleCache\remember;

$result = remember(
    $cache,
    'report.monthly',
    3600,
    static fn () => buildMonthlyReport(),
);
```

Here, `$cache` implements `Psr\SimpleCache\CacheInterface`. The callback runs only when no cached value is available.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
