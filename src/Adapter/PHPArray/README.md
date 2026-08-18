# Array PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/array-adapter/v/stable)](https://packagist.org/packages/cache/array-adapter)
[![Coverage](https://codecov.io/gh/php-cache/array-adapter/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/array-adapter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package provides in-memory PSR-6 and PSR-16 cache implementations. The pool supports tags and hierarchical keys.

Values live only for the current PHP process. This adapter works well in tests and short-lived scripts.

## Installation

```bash
composer require cache/array-adapter:^2.0
```

## Usage

```php
use Cache\Adapter\PHPArray\ArrayCachePool;

$pool = new ArrayCachePool();
```

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
