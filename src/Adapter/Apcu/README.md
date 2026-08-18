# APCu PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/apcu-adapter/v/stable)](https://packagist.org/packages/cache/apcu-adapter)
[![Coverage](https://codecov.io/gh/php-cache/apcu-adapter/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/apcu-adapter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package provides PSR-6 and PSR-16 cache implementations backed by APCu. The pool also supports tags.

## Installation

Install the package and enable the APCu extension:

```bash
composer require cache/apcu-adapter:^2.0
```

## Usage

```php
use Cache\Adapter\Apcu\ApcuCachePool;

$pool = new ApcuCachePool();
```

## Upgrading to version 2

Version 2 stores native arrays instead of serialized strings. Older workers cannot read entries written by version 2.

Stop all workers, clear APCu, and then deploy version 2. Follow the same sequence before a rollback.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
