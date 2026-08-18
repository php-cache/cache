<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Predis;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Hierarchy\HierarchicalCachePoolTrait;
use Cache\Hierarchy\HierarchicalPoolInterface;
use Predis\ClientInterface as Client;
use Predis\Response\Status;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class PredisCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;

    protected Client $cache;

    public function __construct(Client $cache)
    {
        $this->cache = $cache;
    }

    protected function fetchObjectFromCache(string $key): array
    {
        return $this->decodeCacheItem($this->cache->get($this->getHierarchyKey($key))) ?? [false, null, [], null];
    }

    protected function clearAllObjectsFromCache(): bool
    {
        return $this->isOkResponse($this->cache->flushdb());
    }

    protected function clearOneObjectFromCache(string $key): bool
    {
        $path = null;
        $keyString = $this->getHierarchyKey($key, $path);
        if (null !== $path) {
            $this->cache->incr($path);
        }
        $this->clearHierarchyKeyCache();

        $deleted = $this->cache->del($keyString);

        return $deleted >= 0;
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        if (null !== $ttl && $ttl < 0) {
            return false;
        }

        $key = $this->getHierarchyKey($item->getKey());
        $data = serialize([true, $item->get(), $item->getTags(), $item->getExpirationTimestamp()]);

        if (null === $ttl || 0 === $ttl) {
            return $this->isOkResponse($this->cache->set($key, $data));
        }

        return $this->isOkResponse($this->cache->setex($key, $ttl, $data));
    }

    public function getDirectValue(string $key): mixed
    {
        return $this->cache->get($key);
    }

    protected function appendListItem(string $name, string $value): bool
    {
        $added = $this->cache->sadd($name, [$value]);

        return $added >= 0;
    }

    protected function getList(string $name): array
    {
        $items = $this->cache->smembers($name);

        return array_values(array_filter($items, is_string(...)));
    }

    protected function removeList(string $name): bool
    {
        $deleted = $this->cache->del($name);

        return $deleted >= 0;
    }

    protected function removeListItem(string $name, string $key): bool
    {
        $removed = $this->cache->srem($name, $key);

        return $removed >= 0;
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

    private function isOkResponse(mixed $response): bool
    {
        if ($response instanceof Status) {
            return 'OK' === $response->getPayload();
        }

        return 'OK' === $response;
    }
}
