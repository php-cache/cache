<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Encryption;

use Cache\Adapter\Common\Exception\InvalidArgumentException;
use Cache\TagInterop\TaggableCacheItemInterface;
use Cache\TagInterop\TaggableCacheItemPoolInterface;
use Defuse\Crypto\Key;
use Psr\Cache\CacheItemInterface;

/**
 * Wraps a CacheItemInterface with EncryptedItemDecorator.
 *
 * @author Daniel Bannert <d.bannert@anolilab.de>
 */
class EncryptedCachePool implements TaggableCacheItemPoolInterface
{
    private TaggableCacheItemPoolInterface $cachePool;

    private Key $key;

    private readonly object $owner;

    public function __construct(TaggableCacheItemPoolInterface $cachePool, Key $key)
    {
        $this->cachePool = $cachePool;
        $this->key = $key;
        $this->owner = new \stdClass();
    }

    public function getItem(string $key): TaggableCacheItemInterface
    {
        $item = $this->cachePool->getItem($key);

        if ($item instanceof EncryptedItemDecorator && $item->isOwnedBy($this->owner)) {
            return $item;
        }

        return new EncryptedItemDecorator($item, $this->key, $this->owner);
    }

    /**
     * @param array<array-key, string> $keys
     *
     * @return iterable<string, TaggableCacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        return $this->wrapItems($this->cachePool->getItems($keys));
    }

    /**
     * @param iterable<array-key, CacheItemInterface> $items
     *
     * @return \Generator<string, TaggableCacheItemInterface>
     */
    private function wrapItems(iterable $items): \Generator
    {
        foreach ($items as $inner) {
            if (!$inner instanceof EncryptedItemDecorator || !$inner->isOwnedBy($this->owner)) {
                if (!$inner instanceof TaggableCacheItemInterface) {
                    throw new \UnexpectedValueException('A taggable cache pool returned a non-taggable cache item.');
                }

                $inner = new EncryptedItemDecorator($inner, $this->key, $this->owner);
            }

            yield $inner->getKey() => $inner;
        }
    }

    public function hasItem(string $key): bool
    {
        return $this->getItem($key)->isHit();
    }

    public function clear(): bool
    {
        return $this->cachePool->clear();
    }

    public function deleteItem(string $key): bool
    {
        return $this->cachePool->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->cachePool->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof EncryptedItemDecorator || !$item->isOwnedBy($this->owner)) {
            throw new InvalidArgumentException('Cache items are not transferable between pools. Item MUST implement EncryptedItemDecorator.');
        }

        return $this->cachePool->save($item->getCacheItem());
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$item instanceof EncryptedItemDecorator || !$item->isOwnedBy($this->owner)) {
            throw new InvalidArgumentException('Cache items are not transferable between pools. Item MUST implement EncryptedItemDecorator.');
        }

        return $this->cachePool->saveDeferred($item->getCacheItem());
    }

    public function commit(): bool
    {
        return $this->cachePool->commit();
    }

    /**
     * @param array<array-key, string> $tags
     */
    public function invalidateTags(array $tags): bool
    {
        return $this->cachePool->invalidateTags($tags);
    }

    public function invalidateTag(string $tag): bool
    {
        return $this->cachePool->invalidateTag($tag);
    }
}
