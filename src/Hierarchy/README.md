# Hierarchical PSR-6 cache pools

[![Latest Stable Version](https://poser.pugx.org/cache/hierarchical-cache/v/stable)](https://packagist.org/packages/cache/hierarchical-cache)
[![Coverage](https://codecov.io/gh/php-cache/hierarchical-cache/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/hierarchical-cache)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package defines hierarchical PSR-6 pools. A hierarchical pool can invalidate a branch without enumerating every child key.

For example, deleting `|users|42|followers` also invalidates keys below that path.

## Installation

```bash
composer require cache/hierarchical-cache:^2.0
```

## Usage

Read the [hierarchical cache guide](https://www.php-cache.com/en/latest/hierarchy/) for key syntax and invalidation behavior.

## Implementing a pool

Adapter authors can use `HierarchicalCachePoolTrait`. Read the [implementation guide](https://www.php-cache.com/en/latest/implementing-cache-pools/hierarchy/) before storing or deleting hierarchical keys.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
