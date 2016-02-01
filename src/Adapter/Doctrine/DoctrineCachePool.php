<?php

/*
 * This file is part of php-cache\doctrine-adapter package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Doctrine;

use Cache\Adapter\Common\AbstractCachePool;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\FlushableCache;
use Psr\Cache\CacheItemInterface;

/**
 * This is a bridge between PSR-6 and aDoctrine cache.
 *
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class DoctrineCachePool extends AbstractCachePool
{
    /**
     * @type Cache
     */
    private $cache;

    /**
     * @param Cache $cache
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    protected function fetchObjectFromCache($key)
    {
        if (false === $data = $this->cache->fetch($key)) {
            return [false, null];
        }

        return [true, unserialize($data)];
    }

    protected function clearAllObjectsFromCache()
    {
        if ($this->cache instanceof FlushableCache) {
            return $this->cache->flushAll();
        }

        return false;
    }

    protected function clearOneObjectFromCache($key)
    {
        return $this->cache->delete($key);
    }

    protected function storeItemInCache($key, CacheItemInterface $item, $ttl)
    {
        if ($ttl === null) {
            $ttl = 0;
        }

        return $this->cache->save($key, serialize($item->get()), $ttl);
    }

    /**
     * @return Cache
     */
    public function getCache()
    {
        return $this->cache;
    }
}
