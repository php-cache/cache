<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Memcache;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Adapter\Common\TagSupportWithArray;

class MemcacheCachePool extends AbstractCachePool
{
    use TagSupportWithArray;

    protected \Memcache $cache;

    public function __construct(\Memcache $cache)
    {
        $this->cache = $cache;
    }

    protected function fetchObjectFromCache(string $key): array
    {
        return $this->decodeCacheItem($this->cache->get($key)) ?? [false, null, [], null];
    }

    protected function clearAllObjectsFromCache(): bool
    {
        return $this->cache->flush();
    }

    protected function clearOneObjectFromCache(string $key): bool
    {
        $this->cache->delete($key);

        return true;
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        if (null === $ttl) {
            $ttl = 0;
        } elseif ($ttl < 0) {
            return false;
        } elseif ($ttl > 86400 * 30) {
            $ttl = time() + $ttl;
        }

        $data = serialize([true, $item->get(), $item->getTags(), $item->getExpirationTimestamp()]);

        return $this->cache->set($item->getKey(), $data, 0, $ttl);
    }

    public function getDirectValue(string $name): mixed
    {
        return $this->cache->get($name);
    }

    public function setDirectValue(string $name, mixed $value): bool
    {
        return $this->cache->set($name, $value);
    }

    /**
     * @return array{true, mixed, array<string, string>, int|null}|null
     */
    private function decodeCacheItem(mixed $payload): ?array
    {
        if (!is_string($payload)) {
            return null;
        }

        try {
            $cacheItem = @unserialize($payload);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($cacheItem) || !array_is_list($cacheItem) || 4 !== count($cacheItem)) {
            return null;
        }

        [$hit, $value, $tags, $expirationTimestamp] = $cacheItem;
        if (true !== $hit || !is_array($tags)) {
            return null;
        }

        $validTags = [];
        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                return null;
            }

            $validTags[$tag] = $tag;
        }

        if (null !== $expirationTimestamp && !is_int($expirationTimestamp)) {
            return null;
        }

        return [true, $value, $validTags, $expirationTimestamp];
    }
}
