# PSR cache session handlers

[![Latest Stable Version](https://poser.pugx.org/cache/session-handler/v/stable)](https://packagist.org/packages/cache/session-handler)
[![Coverage](https://codecov.io/gh/php-cache/session-handler/branch/master/graph/badge.svg)](https://codecov.io/gh/php-cache/session-handler)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package stores PHP sessions in a PSR-6 or PSR-16 cache. An application-supplied lock serializes access to each session.

## Installation

```bash
composer require cache/session-handler:^2.0
```

## Usage

```php
use Cache\SessionHandler\Psr6SessionHandler;

$lock = new ApplicationSessionLock($atomicLockBackend);

$handler = new Psr6SessionHandler($pool, $lock, [
    'ttl' => 3600,
    'prefix' => 'session.',
]);

session_set_save_handler($handler, true);
session_start();
```

In this example, `$pool` implements `Psr\Cache\CacheItemPoolInterface`. The application class `ApplicationSessionLock` implements `SessionLockInterface`.

The PHP Cache Bundle supplies this lock through Symfony Lock. Applications that use the session-handler package directly must provide their own implementation.

Use `Psr16SessionHandler` with a `Psr\SimpleCache\CacheInterface` instead. Both handlers use the same lock contract.

## Lock contract

The lock implementation must acquire exclusive locks atomically across every process and node that can access the cache.

`acquire()` returns `true` only after it holds the session lock. A `false` result stops the read before cache access.

`release()` must release only the lock owned by the current request. Track an ownership token when the backend requires one.

Configure a lease or equivalent recovery mechanism in the backend. This prevents crashed requests from leaving permanent locks.

PSR-6 and PSR-16 do not provide atomic lock operations. Do not build a lock with a cache read followed by a cache write.

The handler acquires the lock during `validateId()` or `read()`. It keeps that lock through `write()` and `updateTimestamp()`.

Every mutation verifies that it holds the lock for the same session ID. This also protects writes after session ID regeneration.

If a mutation cannot acquire its lock, it returns `false` without accessing the cache.

`close()` and `destroy()` release the lock. The handler also releases it when a storage operation throws an exception.

## Migrating to 2.0

Version 2.0 requires a `SessionLockInterface` as the second constructor argument. Pass the options array as the third argument.

```php
$psr6Handler = new Psr6SessionHandler($pool, $lock, $options);
$psr16Handler = new Psr16SessionHandler($simpleCache, $lock, $options);
```

## Contributing

Send pull requests to the [main repository](https://github.com/php-cache/cache). Report issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).
