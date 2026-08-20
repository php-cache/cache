# Void PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/void-adapter/v/stable)](https://packagist.org/packages/cache/void-adapter)
[![Coverage](https://codecov.io/gh/php-cache/void-adapter/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/void-adapter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package provides PSR-6 and PSR-16 cache implementations that never retain values. Reads always miss, while writes and deletions succeed.

Use this adapter when an application requires a cache interface but caching must remain disabled.

## Installation

```bash
composer require cache/void-adapter:^3.0
```

## Usage

```php
use Cache\Adapter\Void\VoidCachePool;

$pool = new VoidCachePool();
```

Version 3 requires `cache/adapter-common` 3. The pool retains no cache data, so an upgrade does not require a cache clear.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
