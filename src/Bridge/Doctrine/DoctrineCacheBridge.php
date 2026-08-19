<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Bridge\Doctrine;

use Doctrine\Common\Cache\CacheProvider;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * This is a bridge between a Doctrine cache and PSR6.
 *
 * @author Aaron Scherer <aequasi@gmail.com>
 */
class DoctrineCacheBridge extends CacheProvider
{
    private CacheItemPoolInterface $cachePool;

    /**
     * DoctrineCacheBridge constructor.
     */
    public function __construct(CacheItemPoolInterface $cachePool)
    {
        $this->cachePool = $cachePool;
    }

    public function getCachePool(): CacheItemPoolInterface
    {
        return $this->cachePool;
    }

    /**
     * Fetches an entry from the cache.
     *
     * @param string $id the id of the cache entry to fetch
     *
     * @return mixed|false the cached data or FALSE, if no cache entry exists for the given id
     */
    protected function doFetch($id): mixed
    {
        $item = $this->getItemWithPortableKeyFallback($id);

        if ($item->isHit()) {
            return $item->get();
        }

        return false;
    }

    /**
     * Tests if an entry exists in the cache.
     *
     * @param string $id the cache id of the entry to check for
     *
     * @return bool TRUE if a cache entry exists for the given cache id, FALSE otherwise
     */
    protected function doContains($id): bool
    {
        return $this->withPortableKeyFallback(
            $id,
            fn (string $key): bool => $this->cachePool->hasItem($key)
        );
    }

    /**
     * Puts data into the cache.
     *
     * @param string $id       the cache id
     * @param mixed  $data     the cache entry/data
     * @param int    $lifeTime The lifetime. If != 0, sets a specific lifetime for this
     *                         cache entry (0 => infinite lifeTime).
     *
     * @return bool TRUE if the entry was successfully stored in the cache, FALSE otherwise
     */
    protected function doSave($id, $data, $lifeTime = 0): bool
    {
        $item = $this->getItemWithPortableKeyFallback($id);
        $item->set($data);

        if (0 !== $lifeTime) {
            $item->expiresAfter($lifeTime);
        }

        return $this->cachePool->save($item);
    }

    /**
     * Deletes a cache entry.
     *
     * @param string $id the cache id
     *
     * @return bool TRUE if the cache entry was successfully deleted, FALSE otherwise
     */
    protected function doDelete($id): bool
    {
        return $this->withPortableKeyFallback(
            $id,
            fn (string $key): bool => $this->cachePool->deleteItem($key)
        );
    }

    /**
     * Flushes all cache entries.
     *
     * @return bool TRUE if the cache entries were successfully flushed, FALSE otherwise
     */
    protected function doFlush(): bool
    {
        return $this->cachePool->clear();
    }

    /**
     * Retrieves cached information from the data store.
     *
     * @since 2.2
     *
     * @return array<string, mixed>|null an associative array with server statistics if available
     */
    protected function doGetStats(): ?array
    {
        return null;
    }

    /**
     * We need to make sure we do not use any characters not supported.
     */
    private function normalizeKey(string $key): string
    {
        if (preg_match('|[\{\}\(\)/\\\@\:]|', $key)) {
            return preg_replace('|[\{\}\(\)/\\\@\:]|', '_', $key) ?? $key;
        }

        return $key;
    }

    private function getItemWithPortableKeyFallback(string $key): CacheItemInterface
    {
        return $this->withPortableKeyFallback(
            $key,
            fn (string $normalizedKey): CacheItemInterface => $this->cachePool->getItem($normalizedKey)
        );
    }

    /**
     * @template T
     *
     * @param callable(string): T $operation
     *
     * @return T
     */
    private function withPortableKeyFallback(string $key, callable $operation): mixed
    {
        try {
            return $operation($this->normalizeKey($key));
        } catch (InvalidArgumentException) {
            return $operation(hash('sha256', $key));
        }
    }
}
