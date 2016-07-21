<?php

/*
 * This file is part of php-cache organization.
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
    protected $cache;

    /**
     * @param Memcache $cache
     */
    public function __construct(Memcache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        if (false === $result = unserialize($this->cache->get($key))) {
            return [false, null, []];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllObjectsFromCache()
    {
        return $this->cache->flush();
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key)
    {
        $this->cache->delete($key);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache(CacheItemInterface $item, $ttl)
    {
        $data = serialize([true, $item->get(), []]);

        return $this->cache->set($item->getKey(), $data, 0, $ttl ?: 0);
    }
}
