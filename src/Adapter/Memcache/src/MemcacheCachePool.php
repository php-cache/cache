<?php

/*
 * This file is part of php-cache\memcache-adapter package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Memcache;

use Cache\Adapter\Common\AbstractCachePool;
use Memcache;
use Psr\Cache\CacheItemInterface;

class MemcacheCachePool extends AbstractCachePool
{
    /**
     * @type Memcache
     */
    private $cache;

    public function __construct(Memcache $cache)
    {
        $this->cache = $cache;
    }

    protected function fetchObjectFromCache($key)
    {
        return $this->cache->get($key);
    }

    protected function clearAllObjectsFromCache()
    {
        return $this->cache->flush();
    }

    protected function clearOneObjectFromCache($key)
    {
        $this->cache->delete($key);

        return true;
    }

    protected function storeItemInCache($key, CacheItemInterface $item, $ttl)
    {
        return $this->cache->set($key, $item, 0, $ttl ?: 0);
    }
}
