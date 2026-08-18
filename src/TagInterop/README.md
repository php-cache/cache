# Tag interfaces for PSR-6

[![Latest Stable Version](https://poser.pugx.org/cache/tag-interop/v/stable)](https://packagist.org/packages/cache/tag-interop)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package defines shared interfaces for tagging PSR-6 cache items and invalidating them by tag.

## Installation

```bash
composer require cache/tag-interop:^2.0
```

## Usage

Tag-aware pools implement `TaggableCacheItemPoolInterface`. Their items implement `TaggableCacheItemInterface`.

```php
use Cache\TagInterop\TaggableCacheItemPoolInterface;

function invalidateProducts(TaggableCacheItemPoolInterface $pool): bool
{
    return $pool->invalidateTag('products');
}
```

Version 2 extends the interfaces from `psr/cache` 3.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
