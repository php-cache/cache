<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Chain;

use Cache\Taggable\TaggablePoolInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CachePoolChain implements CacheItemPoolInterface, TaggablePoolInterface
{
    /**
     * @type CacheItemPoolInterface[]
     */
    private $pools;

    /**
     * @param array $pools
     */
    public function __construct(array $pools)
    {
        if (empty($pools)) {
            throw new \LogicException('At least one pool is required for the chain.');
        }
        $this->pools = $pools;
    }

    public function getItem($key, array $tags = [])
    {
        foreach ($this->pools as $pool) {
            $item = $pool->getItem($key, $tags);
            if ($item->isHit()) {
                return $item;
            }
        }

        // Return the item from the last pool
        return $item;
    }

    public function getItems(array $keys = [], array $tags = [])
    {
        $hits = [];
        foreach ($this->pools as $pool) {
            $items = $pool->getItems($keys, $tags);
            /** @type CacheItemInterface $item */
            foreach ($items as $item) {
                if ($item->isHit()) {
                    $hits[$item->getKey()] = $item;
                }
            }

            if (count($hits) === count($keys)) {
                return $hits;
            }
        }

        // We need to accept that some items where not hits.
        return array_merge($hits, $items);
    }

    public function hasItem($key, array $tags = [])
    {
        foreach ($this->pools as $pool) {
            if ($pool->hasItem($key, $tags)) {
                return true;
            }
        }

        return false;
    }

    public function clear(array $tags = [])
    {
        $result = true;
        foreach ($this->pools as $pool) {
            $result = $result && $pool->clear($tags);
        }

        return $result;
    }

    public function deleteItem($key, array $tags = [])
    {
        $result = true;
        foreach ($this->pools as $pool) {
            $result = $result && $pool->deleteItem($key, $tags);
        }

        return $result;
    }

    public function deleteItems(array $keys, array $tags = [])
    {
        $result = true;
        foreach ($this->pools as $pool) {
            $result = $result && $pool->deleteItems($keys, $tags);
        }

        return $result;
    }

    public function save(CacheItemInterface $item)
    {
        $result = true;
        foreach ($this->pools as $pool) {
            $result = $result && $pool->save($item);
        }

        return $result;
    }

    public function saveDeferred(CacheItemInterface $item)
    {
        $result = true;
        foreach ($this->pools as $pool) {
            $result = $result && $pool->saveDeferred($item);
        }

        return $result;
    }

    public function commit()
    {
        $result = true;
        foreach ($this->pools as $pool) {
            $result = $result && $pool->commit();
        }

        return $result;
    }
}
