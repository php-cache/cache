<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Prefixed;

use Cache\Prefixed\Exception\InvalidArgumentException;
use Cache\TagInterop\TaggableCacheItemInterface;
use Cache\TagInterop\TaggableCacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Prefix all cache items with a string.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class PrefixedCachePool implements CacheItemPoolInterface
{
    use PrefixedUtilityTrait;

    private CacheItemPoolInterface $cachePool;

    private readonly object $owner;

    /**
     * @return ($cachePool is TaggableCacheItemPoolInterface ? TaggablePrefixedCachePool : self)
     */
    public static function create(CacheItemPoolInterface $cachePool, string $prefix): self
    {
        if ($cachePool instanceof TaggableCacheItemPoolInterface) {
            return new TaggablePrefixedCachePool($cachePool, $prefix);
        }

        return new self($cachePool, $prefix);
    }

    public function __construct(CacheItemPoolInterface $cachePool, string $prefix)
    {
        $this->cachePool = $cachePool;
        $this->prefix = $this->encodePrefix($prefix);
        $this->owner = new \stdClass();
    }

    public function getItem(string $key): CacheItemInterface
    {
        $originalKey = $key;
        $this->prefixValue($key);

        return $this->wrapItem($originalKey, $this->cachePool->getItem($key));
    }

    /**
     * @param array<array-key, string> $keys
     *
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        $prefixedKeys = $this->prefixValues($keys);

        $originalKeys = [];
        foreach ($prefixedKeys as $index => $prefixedKey) {
            $originalKeys["\0".$prefixedKey] = $keys[$index];
        }

        return $this->wrapItems($prefixedKeys, $originalKeys);
    }

    /**
     * @param array<array-key, string> $prefixedKeys
     * @param array<string, string>    $originalKeys
     *
     * @return \Generator<string, CacheItemInterface>
     */
    private function wrapItems(array $prefixedKeys, array $originalKeys): \Generator
    {
        foreach ($this->cachePool->getItems($prefixedKeys) as $item) {
            $mappedKey = "\0".$item->getKey();
            if (!\array_key_exists($mappedKey, $originalKeys)) {
                continue;
            }

            $originalKey = $originalKeys[$mappedKey];
            yield $originalKey => $this->wrapItem($originalKey, $item);
        }
    }

    private function wrapItem(string $key, CacheItemInterface $item): PrefixedCacheItem
    {
        if ($item instanceof TaggableCacheItemInterface) {
            return new TaggablePrefixedCacheItem($key, $item, $this->owner);
        }

        return new PrefixedCacheItem($key, $item, $this->owner);
    }

    public function hasItem(string $key): bool
    {
        $this->prefixValue($key);

        return $this->cachePool->hasItem($key);
    }

    public function clear(): bool
    {
        return $this->cachePool->clear();
    }

    public function deleteItem(string $key): bool
    {
        $this->prefixValue($key);

        return $this->cachePool->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        $keys = $this->prefixValues($keys);

        return $this->cachePool->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof PrefixedCacheItem || !$item->isOwnedBy($this->owner)) {
            throw new InvalidArgumentException('Cache items are not transferable between pools.');
        }

        return $this->cachePool->save($item->unwrap());
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$item instanceof PrefixedCacheItem || !$item->isOwnedBy($this->owner)) {
            throw new InvalidArgumentException('Cache items are not transferable between pools.');
        }

        return $this->cachePool->saveDeferred($item->unwrap());
    }

    public function commit(): bool
    {
        return $this->cachePool->commit();
    }
}
