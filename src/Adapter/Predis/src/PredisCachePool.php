<?php

/*
 * This file is part of php-cache\predis-adapter package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Predis;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Hierarchy\HierarchicalCachePoolTrait;
use Cache\Hierarchy\HierarchicalPoolInterface;
use Predis\Client;
use Psr\Cache\CacheItemInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class PredisCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;

    /**
     * @type Client
     */
    private $cache;

    /**
     * @param Client $cache
     */
    public function __construct(Client $cache)
    {
        $this->cache = $cache;
    }

    protected function fetchObjectFromCache($key)
    {
        return unserialize($this->cache->get($this->getHierarchyKey($key)));
    }

    protected function clearAllObjectsFromCache()
    {
        return 'OK' === $this->cache->flushdb()->getPayload();
    }

    protected function clearOneObjectFromCache($key)
    {
        // We have to commit here to be able to remove deferred hierarchy items
        $this->commit();

        $keyString = $this->getHierarchyKey($key, $path);
        $this->cache->incr($path);
        $this->clearHierarchyKeyCache();

        return $this->cache->del($keyString) >= 0;
    }

    protected function storeItemInCache($key, CacheItemInterface $item, $ttl)
    {
        $key = $this->getHierarchyKey($key);
        if ($ttl === null) {
            return 'OK' === $this->cache->set($key, serialize($item))->getPayload();
        }

        return 'OK' === $this->cache->setex($key, $ttl, serialize($item))->getPayload();
    }

    protected function getValueFormStore($key)
    {
        return $this->cache->get($key);
    }
}
