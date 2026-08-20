# Encrypted PSR-6 cache pool

[![Latest Stable Version](https://poser.pugx.org/cache/encryption-cache/v/stable)](https://packagist.org/packages/cache/encryption-cache)
[![Coverage](https://codecov.io/gh/php-cache/encryption-cache/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/encryption-cache)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package encrypts values stored through a taggable PSR-6 cache pool. Encryption adds CPU and storage overhead, so use it only when cached values need protection at rest.

## Installation

```bash
composer require cache/encryption-cache:^2.1
```

## Usage

```php
use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Encryption\EncryptedCachePool;
use Defuse\Crypto\Key;

$key = Key::createNewRandomKey();
$pool = new EncryptedCachePool(new ArrayCachePool(), $key);

$item = $pool->getItem('account.42');
$item->set(['email' => 'user@example.com']);
$pool->save($item);
```

The wrapped pool must implement `TaggableCacheItemPoolInterface`. Persist the encryption key outside the cache and load the same key for every request.

Version 2.1 supports `cache/adapter-common` 2 and 3. Follow the wrapped adapter's upgrade steps before deploying or rolling back.

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
