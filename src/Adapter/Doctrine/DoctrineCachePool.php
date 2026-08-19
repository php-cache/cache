<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Doctrine;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Adapter\Common\PhpUnserializer;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\FlushableCache;

/**
 * This is a bridge between PSR-6 and aDoctrine cache.
 *
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class DoctrineCachePool extends AbstractCachePool
{
    protected Cache $cache;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    protected function fetchObjectFromCache(string $key): array
    {
        $payload = $this->cache->fetch($key);
        if (!\is_string($payload)) {
            return [false, null, [], null];
        }

        if (!PhpUnserializer::unserialize($payload, $record)) {
            return [false, null, [], null];
        }

        if (!\is_array($record) || !array_is_list($record) || 4 !== \count($record) || true !== $record[0]) {
            return [false, null, [], null];
        }

        $tags = $record[2];
        if (!\is_array($tags)) {
            return [false, null, [], null];
        }

        $decodedTags = [];
        foreach ($tags as $tag) {
            if (!\is_string($tag)) {
                return [false, null, [], null];
            }

            $decodedTags[$tag] = $tag;
        }

        $expiration = $record[3];
        if (!\is_int($expiration) && null !== $expiration) {
            return [false, null, [], null];
        }

        return [true, $record[1], $decodedTags, $expiration];
    }

    protected function clearAllObjectsFromCache(): bool
    {
        if ($this->cache instanceof FlushableCache) {
            return $this->cache->flushAll();
        }

        return false;
    }

    protected function clearOneObjectFromCache(string $key): bool
    {
        return $this->cache->delete($key) || !$this->cache->contains($key);
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        if (null === $ttl) {
            $ttl = 0;
        }

        $data = serialize([true, $item->get(), $item->getTags(), $item->getExpirationTimestamp()]);

        return $this->cache->save($item->getKey(), $data, $ttl);
    }

    public function getCache(): Cache
    {
        return $this->cache;
    }

    protected function getList(string $name): array
    {
        $storedList = $this->cache->fetch($name);
        if (!\is_array($storedList)) {
            return [];
        }

        $list = [];
        foreach ($storedList as $item) {
            if (!\is_string($item)) {
                return [];
            }

            $list[] = $item;
        }

        return $list;
    }

    protected function removeList(string $name): bool
    {
        return $this->cache->delete($name) || !$this->cache->contains($name);
    }

    protected function appendListItem(string $name, string $key): bool
    {
        $list = $this->getList($name);
        $list[] = $key;

        return $this->cache->save($name, $list);
    }

    protected function removeListItem(string $name, string $key): bool
    {
        $list = $this->getList($name);
        foreach ($list as $i => $item) {
            if ($item === $key) {
                unset($list[$i]);
            }
        }

        return $this->cache->save($name, $list);
    }
}
