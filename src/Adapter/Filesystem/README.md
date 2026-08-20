# Filesystem PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/filesystem-adapter/v/stable)](https://packagist.org/packages/cache/filesystem-adapter)
[![Coverage](https://codecov.io/gh/php-cache/filesystem-adapter/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/filesystem-adapter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package provides PSR-6 and PSR-16 cache implementations backed by [Flysystem](https://flysystem.thephpleague.com/). It supports Flysystem 2.x and 3.x.

## Installation

```bash
composer require cache/filesystem-adapter:^3.0
```

## Usage

```php
use Cache\Adapter\Filesystem\FilesystemCachePool;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

$filesystem = new Filesystem(new LocalFilesystemAdapter(__DIR__.'/storage'));
$pool = new FilesystemCachePool($filesystem);
```

The pool stores entries in the `cache` directory by default. Pass another directory to the constructor when needed:

```php
$pool = new FilesystemCachePool($filesystem, 'path/to/cache');
```

Version 3 hashes each key with SHA-256 and stores it in one of 256 shard directories. This keeps filenames portable and limits directory size.

Use the accessors when an extending app needs to inspect or replace the Flysystem instance or cache directory:

```php
$pool->setFolder('tenant/cache');
$pool->setFilesystem($replacementFilesystem);

$folder = $pool->getFolder();
$filesystem = $pool->getFilesystem();
```

`setFolder()` normalizes forward and backward slashes. It removes empty and current-directory segments such as `.`.

It rejects folders that resolve to the Flysystem root or contain a parent-directory segment such as `cache/..`. The constructor applies the same checks to its folder argument.

`setFilesystem()` creates the current cache directory on the replacement filesystem.

## Custom file layouts

Subclass `FilesystemCachePool` and override `getFilePath()` to customize the default sharded layout. Call the parent method first to keep the portable key hash.

```php
final class ShardedFilesystemCachePool extends FilesystemCachePool
{
    protected function getFilePath(string $key): string
    {
        $path = parent::getFilePath($key);

        return $this->getFolder().'/tenant/'.substr($path, strlen($this->getFolder()) + 1);
    }
}
```

Return a deterministic path below `getFolder()` for every item and internal tag-index key. Use filesystem-portable path components, and do not derive them directly from untrusted cache keys. Paths outside the configured cache folder are not found by `clear()`.

Use a dedicated cache folder. `clear()` searches nested directories and removes every file below the configured folder.

## Upgrading to version 3

Version 3 stores files under SHA-256-based shard paths instead of raw cache-key filenames. It also stores generation metadata for tagged items. Version 2 workers cannot safely share either format.

Stop or drain all workers, clear the cache directory, and then deploy version 3. Follow the same sequence before a rollback.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
