<?php

/*
 * This file is part of php-cache organization.
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
use Cache\Taggable\TaggableItemInterface;
use Cache\Taggable\TaggablePoolInterface;
use Cache\Taggable\TaggablePoolTrait;
use Psr\Cache\CacheItemInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class RedisCachePool extends AbstractCachePool implements HierarchicalPoolInterface, TaggablePoolInterface
{
    use HierarchicalCachePoolTrait;
    use TaggablePoolTrait;

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
            return [false, null, []];
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
        $this->preRemoveItem($key);
        $keyString = $this->getHierarchyKey($key, $path);
        $this->cache->incr($path);
        $this->clearHierarchyKeyCache();

        return $this->cache->del($keyString) >= 0;
    }

    protected function storeItemInCache(CacheItemInterface $item, $ttl)
    {
        $key  = $this->getHierarchyKey($item->getKey());
        $data = serialize([true, $item->get(), $item->getTags()]);
        if ($ttl === null || $ttl === 0) {
            return $this->cache->set($key, $data);
        }

        return $this->cache->setex($key, $ttl, $data);
    }

    public function save(CacheItemInterface $item)
    {
        if ($item instanceof TaggableItemInterface) {
            $this->saveTags($item);
        }

        return parent::save($item);
    }

    protected function getValueFormStore($key)
    {
        return $this->cache->get($key);
    }

    protected function appendListItem($name, $value)
    {
        $this->cache->lPush($name, $value);
    }

    protected function getList($name)
    {
        return $this->cache->lRange($name, 0, -1);
    }

    protected function removeList($name)
    {
        return $this->cache->del($name);
    }

    protected function removeListItem($name, $key)
    {
        return $this->cache->lrem($name, $key, 0);
    }
}
