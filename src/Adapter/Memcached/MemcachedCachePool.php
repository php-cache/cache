<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Memcached;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\CacheItem;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Adapter\Common\TagSupportWithArray;
use Cache\Hierarchy\HierarchicalCachePoolTrait;
use Cache\Hierarchy\HierarchicalPoolInterface;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class MemcachedCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;
    use TagSupportWithArray;

    protected \Memcached $cache;

    public function __construct(\Memcached $cache)
    {
        $this->cache = $cache;
        $this->cache->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
    }

    protected function fetchObjectFromCache(string $key): array
    {
        return $this->decodeCacheItem($this->cache->get($this->getHierarchyKey($key))) ?? [false, null, [], null];
    }

    protected function clearAllObjectsFromCache(): bool
    {
        return $this->cache->flush();
    }

    protected function clearOneObjectFromCache(string $key): bool
    {
        $deferredCommitted = $this->commit();
        $path = null;
        $key = $this->getHierarchyKey($key, $path);
        $generationAdvanced = true;
        if (null !== $path) {
            $generationAdvanced = false !== $this->cache->increment($path, 1, 0);
        }
        $this->clearHierarchyKeyCache();

        $deleted = $this->cache->delete($key)
            || \Memcached::RES_NOTFOUND === $this->cache->getResultCode();

        return $deferredCommitted && $generationAdvanced && $deleted;
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        if (null === $ttl) {
            $ttl = 0;
        } elseif ($ttl < 0) {
            return false;
        } elseif ($ttl > 86400 * 30) {
            // Any time higher than 30 days is interpreted as a unix timestamp date.
            // https://github.com/memcached/memcached/wiki/Programming#expiration
            $ttl = time() + $ttl;
        }

        $key = $this->getHierarchyKey($item->getKey());

        return $this->cache->set($key, serialize([true, $item->get(), $item->getTags(), $item->getExpirationTimestamp()]), $ttl);
    }

    public function getDirectValue(string $name): mixed
    {
        return $this->cache->get($name);
    }

    public function setDirectValue(string $name, mixed $value): bool
    {
        return $this->cache->set($name, $value);
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $validatedKeys = [];
        foreach ($keys as $key) {
            $key = $this->validateKey($key);
            $validatedKeys["\0".$key] = $key;
        }

        $keys = array_values($validatedKeys);
        $storedKeys = [];
        foreach ($keys as $key) {
            if (!isset($this->deferred[$key])) {
                $storedKeys[] = $key;
            }
        }

        $storedItems = $this->fetchMultipleCacheItems($storedKeys);

        return $this->generateMultipleValues($keys, $storedItems, $default);
    }

    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        $keys = [];
        $preparedValues = [];
        foreach ($values as $key => $value) {
            if (\is_int($key)) {
                $key = (string) $key;
            }
            $key = $this->validateKey($key);
            $mappedKey = "\0".$key;
            $keys[$mappedKey] = $key;
            $preparedValues[$mappedKey] = $value;
        }

        $keys = array_values($keys);
        $expirationTimestamp = $this->getExpirationTimestamp($ttl);
        $now = time();
        if (null !== $expirationTimestamp && $expirationTimestamp <= $now) {
            return $this->deleteMultiple($keys);
        }

        foreach ($keys as $key) {
            unset($this->deferred[$key]);
        }
        $deferredCommitted = $this->commit();
        if ([] === $keys) {
            return $deferredCommitted;
        }

        $storedItems = $this->fetchMultipleCacheItems($keys);
        $items = [];
        foreach ($keys as $key) {
            $items[$this->getHierarchyKey($key)] = serialize([
                true,
                $preparedValues["\0".$key],
                [],
                $expirationTimestamp,
            ]);
        }

        $expiration = $this->getMemcachedExpiration($expirationTimestamp, $now);
        if (!$this->cache->setMulti($items, $expiration)) {
            return false;
        }

        $tagsRemoved = true;
        foreach ($keys as $key) {
            $stored = $storedItems["\0".$key] ?? null;
            if (null === $stored) {
                continue;
            }
            foreach ($stored[2] as $tag) {
                $tagsRemoved = $this->removeListItem($this->getTagKey($tag), $key) && $tagsRemoved;
            }
        }

        return $tagsRemoved && $deferredCommitted;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $validatedKeys = [];
        foreach ($keys as $key) {
            $key = $this->validateKey($key);
            $validatedKeys["\0".$key] = $key;
        }
        $keys = array_values($validatedKeys);

        foreach ($keys as $key) {
            unset($this->deferred[$key]);
        }
        $deferredCommitted = $this->commit();
        if ([] === $keys) {
            return $deferredCommitted;
        }

        $storedItems = $this->fetchMultipleCacheItems($keys);
        $backendKeys = [];
        $generationsAdvanced = true;
        foreach ($keys as $key) {
            $path = null;
            $backendKeys["\0".$key] = $this->getHierarchyKey($key, $path);
            if (null !== $path && false === $this->cache->increment($path, 1, 0)) {
                $generationsAdvanced = false;
            }
        }
        $this->clearHierarchyKeyCache();

        $results = $this->cache->deleteMulti(array_values($backendKeys));
        $deleted = true;
        $tagsRemoved = true;
        foreach ($keys as $key) {
            $result = $results[$backendKeys["\0".$key]] ?? null;
            if (true !== $result && \Memcached::RES_NOTFOUND !== $result) {
                $deleted = false;

                continue;
            }

            $stored = $storedItems["\0".$key] ?? null;
            if (null === $stored) {
                continue;
            }
            foreach ($stored[2] as $tag) {
                $tagsRemoved = $this->removeListItem($this->getTagKey($tag), $key) && $tagsRemoved;
            }
        }

        return $tagsRemoved && $deleted && $deferredCommitted && $generationsAdvanced;
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, array{true, mixed, array<string, string>, int|null}|null>
     */
    private function fetchMultipleCacheItems(array $keys): array
    {
        if ([] === $keys) {
            return [];
        }

        $backendKeys = [];
        foreach ($keys as $key) {
            $backendKeys["\0".$key] = $this->getHierarchyKey($key);
        }

        $payloads = $this->cache->getMulti(array_values($backendKeys), \Memcached::GET_PRESERVE_ORDER);
        if (false === $payloads) {
            throw new \Cache\Adapter\Common\Exception\CachePoolException('Memcached getMulti failed.');
        }

        $storedItems = [];
        foreach ($backendKeys as $mappedKey => $backendKey) {
            $storedItems[$mappedKey] = $this->decodeCacheItem($payloads[$backendKey] ?? null);
        }

        return $storedItems;
    }

    /**
     * @param list<string>                                                            $keys
     * @param array<string, array{true, mixed, array<string, string>, int|null}|null> $storedItems
     *
     * @return \Generator<string, mixed, mixed, void>
     */
    private function generateMultipleValues(array $keys, array $storedItems, mixed $default): \Generator
    {
        foreach ($keys as $key) {
            if (isset($this->deferred[$key])) {
                $item = $this->getItem($key);
                yield $key => $item->isHit() ? $item->get() : $default;

                continue;
            }

            $stored = $storedItems["\0".$key] ?? null;
            if (null === $stored || (null !== $stored[3] && $stored[3] <= time())) {
                yield $key => $default;

                continue;
            }

            yield $key => $stored[1];
        }
    }

    private function getExpirationTimestamp(int|\DateInterval|null $ttl): ?int
    {
        $item = new CacheItem('expiration');
        $item->expiresAfter($ttl);

        return $item->getExpirationTimestamp();
    }

    private function getMemcachedExpiration(?int $expirationTimestamp, int $now): int
    {
        if (null === $expirationTimestamp) {
            return 0;
        }

        $ttl = $expirationTimestamp - $now;

        return $ttl > 86400 * 30 ? $expirationTimestamp : $ttl;
    }

    /**
     * @return array{true, mixed, array<string, string>, int|null}|null
     */
    private function decodeCacheItem(mixed $payload): ?array
    {
        if (!\is_string($payload)) {
            return null;
        }

        try {
            $cacheItem = @unserialize($payload);
        } catch (\Throwable) {
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
}
