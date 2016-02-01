<?php

/*
 * This file is part of php-cache\array-adapter package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\PHPArray;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\CacheItem;
use Cache\Hierarchy\HierarchicalCachePoolTrait;
use Cache\Hierarchy\HierarchicalPoolInterface;
use Psr\Cache\CacheItemInterface;

/**
 * Array cache pool. You could set a limit of how many items you wantt to be stored to avoid memory leaks.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ArrayCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;

    /**
     * @type array
     */
    private $cache;

    /**
     * @type array
     *             A map to hold keys
     */
    private $keyMap = [];

    /**
     * @type int
     *           The maximum number of keys in the map
     */
    private $limit;

    /**
     * @type int
     *           The next key that we should remove from the cache
     */
    private $currentPosition = 0;

    /**
     * @param int   $limit the amount if items stored in the cache. Using a limit will reduce memory leaks.
     * @param array $cache
     */
    public function __construct($limit = null, array &$cache = [])
    {
        $this->cache = &$cache;
        $this->limit = $limit;
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemWithoutGenerateCacheKey($key)
    {
        if (isset($this->deferred[$key])) {
            $item = $this->deferred[$key];

            return is_object($item) ? clone $item : $item;
        }

        return $this->fetchObjectFromCache($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        $storageKey = $this->getHierarchyKey($key);
        if (isset($this->cache[$storageKey])) {
            return $this->cache[$storageKey];
        }

        return new CacheItem($key, false);
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllObjectsFromCache()
    {
        $this->cache = [];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key)
    {
        $this->commit();
        $keyString = $this->getHierarchyKey($key, $path);
        if (isset($this->cache[$path])) {
            $this->cache[$path]++;
        } else {
            $this->cache[$path] = 0;
        }
        $this->clearHierarchyKeyCache();

        unset($this->cache[$keyString]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache($key, CacheItemInterface $item, $ttl)
    {
        $key               = $this->getHierarchyKey($key);
        $this->cache[$key] = $item;

        if ($this->limit !== null) {
            // Remove the oldest value
            if (isset($this->keyMap[$this->currentPosition])) {
                unset($this->cache[$this->keyMap[$this->currentPosition]]);
            }

            // Add the new key to the current position
            $this->keyMap[$this->currentPosition] = $key;

            // Increase the current position
            $this->currentPosition = ($this->currentPosition + 1) % $this->limit;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function getValueFormStore($key)
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
    }
}
