# Predis PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/predis-adapter/v/stable)](https://packagist.org/packages/cache/predis-adapter)
[![Coverage](https://codecov.io/gh/php-cache/predis-adapter/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/predis-adapter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package provides a PSR-6 cache pool backed by [Predis](https://github.com/predis/predis). Use the [Redis adapter](https://github.com/php-cache/redis-adapter) for PhpRedis clients.

## Installation

```bash
composer require cache/predis-adapter:^3.0
```

## Usage

```php
use Cache\Adapter\Predis\PredisCachePool;
use Predis\Client;

$client = new Client('tcp://127.0.0.1:6379');
$pool = new PredisCachePool($client);
```

## Upgrading to version 3

Version 3 stores tag indexes as sorted sets under the reserved `php-cache:tag:` prefix. Each index expires with its longest-lived item and stays persistent while it contains a non-expiring item.

Each tagged item stores a generation snapshot. The sorted set stores the current generation marker with the item index.

After the last indexed item is removed, the generation marker remains for 60 seconds. This lets an immediate rewrite reuse the same generation before the new index member is added.

Version 2 uses Redis sets under `tag!` keys. Version 3 does not read those indexes.

Stop all workers, clear the Redis cache, and then deploy version 3. Follow the same sequence before a rollback.

## Upgrading to version 2

Version 2 stores tag indexes as Redis sets. Older releases use Redis lists for the same keys.

Stop all workers, clear the Redis cache, and then deploy version 2. Follow the same sequence before a rollback.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
