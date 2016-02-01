<?php

/*
 * This file is part of php-cache\redis-adapter package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Redis;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Hierarchy\HierarchicalCachePoolTrait;
use Cache\Hierarchy\HierarchicalPoolInterface;
use Psr\Cache\CacheItemInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class RedisCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;

    /**
     * @type \Redis
     */
    private $cache;

    /**
     * @param \Redis $cache
     */
    public function __construct(\Redis $cache)
    {
        $this->cache = $cache;
    }

    protected function fetchObjectFromCache($key)
    {
        if (false === $result = unserialize($this->cache->get($this->getHierarchyKey($key)))) {
            return [false, null];
        }

        return $result;
    }

    protected function clearAllObjectsFromCache()
    {
        return $this->cache->flushDb();
    }

    protected function clearOneObjectFromCache($key)
    {
        $this->commit();
        $keyString = $this->getHierarchyKey($key, $path);
        $this->cache->incr($path);
        $this->clearHierarchyKeyCache();

        return $this->cache->del($keyString) >= 0;
    }

    protected function storeItemInCache($key, CacheItemInterface $item, $ttl)
    {
        $key  = $this->getHierarchyKey($key);
        $data = serialize([true, $item->get()]);
        if ($ttl === null) {
            return $this->cache->set($key, $data);
        }

        return $this->cache->setex($key, $ttl, $data);
    }

    protected function getValueFormStore($key)
    {
        return $this->cache->get($key);
    }
}
