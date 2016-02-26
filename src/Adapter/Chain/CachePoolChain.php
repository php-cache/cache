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

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $found     = false;
        $result    = null;
        $needsSave = [];

        foreach ($this->pools as $pool) {
            $item = $pool->getItem($key);
            if ($item->isHit()) {
                $found  = true;
                $result = $item;
                break;
            }

            $needsSave[] = $pool;
        }

        if ($found) {
            foreach ($needsSave as $pool) {
                $pool->save($result);
            }

            $item = $result;
        }

        return $item;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = [])
    {
        $hits = [];
        foreach ($this->pools as $pool) {
            $items = $pool->getItems($keys);
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

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        foreach ($this->pools as $pool) {
            if ($pool->hasItem($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $result = true;
        foreach ($this->pools as $pool) {
            $result = $result && $pool->clear();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        $result = true;
        foreach ($this->pools as $pool) {
            $result = $result && $pool->deleteItem($key);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $result = true;
        foreach ($this->pools as $pool) {
            $result = $result && $pool->deleteItems($keys);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        $result = true;
        foreach ($this->pools as $pool) {
            $result = $result && $pool->save($item);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        $result = true;
        foreach ($this->pools as $pool) {
            $result = $result && $pool->saveDeferred($item);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $result = true;
        foreach ($this->pools as $pool) {
            $result = $result && $pool->commit();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function clearTags(array $tags)
    {
        $result = true;
        foreach ($this->pools as $pool) {
            if ($pool instanceof TaggablePoolInterface) {
                $result = $result && $pool->clearTags($tags);
            }
        }

        return $result;
    }
}
