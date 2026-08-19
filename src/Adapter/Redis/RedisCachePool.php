<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Redis;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\Exception\CachePoolException;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Adapter\Common\PhpUnserializer;
use Cache\Hierarchy\HierarchicalCachePoolTrait;
use Cache\Hierarchy\HierarchicalPoolInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class RedisCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;

    protected \Redis|\RedisArray|\RedisCluster $cache;

    /** @var array<string, true> */
    private array $failedListReads = [];

    public function __construct(mixed $cache)
    {
        if (!$cache instanceof \Redis
            && !$cache instanceof \RedisArray
            && !$cache instanceof \RedisCluster
        ) {
            throw new CachePoolException('Cache instance must be of type \Redis, \RedisArray, or \RedisCluster');
        }

        $this->cache = $cache;
    }

    protected function fetchObjectFromCache(string $key): array
    {
        return $this->decodeCacheItem($this->cache->get($this->getHierarchyKey($key))) ?? [false, null, [], null];
    }

    protected function clearAllObjectsFromCache(): bool
    {
        if ($this->cache instanceof \RedisCluster) {
            return $this->clearAllObjectsFromCacheCluster();
        }

        $result = $this->cache->flushDb();

        if (!\is_array($result)) {
            return true === $result;
        }

        $success = true;

        foreach ($result as $serverResult) {
            if (!$serverResult) {
                $success = false;
                break;
            }
        }

        return $success;
    }

    /**
     * Clear all objects from all nodes in the cluster.
     *
     * @return bool false if error
     */
    protected function clearAllObjectsFromCacheCluster(): bool
    {
        if (!$this->cache instanceof \RedisCluster) {
            return false;
        }

        $nodes = $this->cache->_masters();

        foreach ($nodes as $node) {
            if (!$this->cache->flushDB($node)) {
                return false;
            }
        }

        return true;
    }

    protected function clearOneObjectFromCache(string $key): bool
    {
        $path = null;
        $keyString = $this->getHierarchyKey($key, $path);
        $generationAdvanced = true;
        if (null !== $path) {
            $generationAdvanced = false !== $this->cache->incr($path);
        }
        $this->clearHierarchyKeyCache();

        $deleted = $this->cache->del($keyString);

        return $generationAdvanced && \is_int($deleted) && $deleted >= 0;
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        $key = $this->getHierarchyKey($item->getKey());
        $data = serialize([true, $item->get(), $item->getTags(), $item->getExpirationTimestamp()]);
        if (null === $ttl || 0 === $ttl) {
            return $this->isSuccessfulWrite($this->cache->set($key, $data));
        }

        return $this->isSuccessfulWrite($this->cache->setex($key, $ttl, $data));
    }

    public function getDirectValue(string $key): mixed
    {
        return $this->cache->get($key);
    }

    protected function appendListItem(string $name, string $value): bool
    {
        $added = $this->cache->sAdd($name, $value);

        return \is_int($added) && $added >= 0;
    }

    protected function getList(string $name): array
    {
        $items = $this->cache->sMembers($name);
        if (!\is_array($items)) {
            $this->failedListReads[$name] = true;

            return [];
        }

        unset($this->failedListReads[$name]);

        return array_values(array_filter($items, is_string(...)));
    }

    protected function removeList(string $name): bool
    {
        if (isset($this->failedListReads[$name])) {
            unset($this->failedListReads[$name]);

            return false;
        }

        $deleted = $this->cache->del($name);

        return \is_int($deleted) && $deleted >= 0;
    }

    protected function removeListItem(string $name, string $key): bool
    {
        $removed = $this->cache->sRem($name, $key);

        return \is_int($removed) && $removed >= 0;
    }

    /**
     * @return array{true, mixed, array<string, string>, int|null}|null
     */
    private function decodeCacheItem(mixed $payload): ?array
    {
        if (!\is_string($payload)) {
            return null;
        }

        if (!PhpUnserializer::unserialize($payload, $cacheItem)) {
            return null;
        }
        if (!\is_array($cacheItem) || !array_is_list($cacheItem) || 4 !== \count($cacheItem)) {
            return null;
        }

        [$hit, $value, $tags, $expirationTimestamp] = $cacheItem;
        if (true !== $hit || !\is_array($tags)) {
            return null;
        }

        $validTags = [];
        foreach ($tags as $tag) {
            if (!\is_string($tag)) {
                return null;
            }

            $validTags[$tag] = $tag;
        }

        if (null !== $expirationTimestamp && !\is_int($expirationTimestamp)) {
            return null;
        }

        return [true, $value, $validTags, $expirationTimestamp];
    }

    private function isSuccessfulWrite(mixed $response): bool
    {
        return true === $response || 'OK' === $response;
    }
}
