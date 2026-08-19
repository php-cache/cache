# Common cache adapter components

[![Latest Stable Version](https://poser.pugx.org/cache/adapter-common/v/stable)](https://packagist.org/packages/cache/adapter-common)
[![Coverage](https://codecov.io/gh/php-cache/adapter-common/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/adapter-common)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package provides the shared PSR-6 and PSR-16 implementation used by PHP Cache adapters. It also provides reusable tag support.

Application code should install a concrete adapter instead. Adapter authors can install these components directly.

## Installation

```bash
composer require cache/adapter-common:^2.0
```

Version 2 requires PHP 8.2 and `psr/cache` 3. Its PSR-16 API supports `psr/simple-cache` 2 and 3.

## Documentation

Read the [PHP Cache documentation](https://www.php-cache.com/) to learn about adapters, tags, and hierarchical keys.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
