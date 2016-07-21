<?php

/*
 * This file is part of php-cache organization.
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
use Cache\Taggable\TaggableItemInterface;
use Cache\Taggable\TaggablePoolInterface;
use Cache\Taggable\TaggablePoolTrait;
use Predis\Client;
use Psr\Cache\CacheItemInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class PredisCachePool extends AbstractCachePool implements HierarchicalPoolInterface, TaggablePoolInterface
{
    use TaggablePoolTrait;
    use HierarchicalCachePoolTrait;

    /**
     * @type Client
     */
    protected $cache;

    /**
     * @param Client $cache
     */
    public function __construct(Client $cache)
    {
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        if ($item instanceof TaggableItemInterface) {
            $this->saveTags($item);
        }

        return parent::save($item);
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        if (false === $result = unserialize($this->cache->get($this->getHierarchyKey($key)))) {
            return [false, null, []];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllObjectsFromCache()
    {
        return 'OK' === $this->cache->flushdb()->getPayload();
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key)
    {
        // We have to commit here to be able to remove deferred hierarchy items
        $this->commit();

        $this->preRemoveItem($key);
        $keyString = $this->getHierarchyKey($key, $path);
        $this->cache->incr($path);
        $this->clearHierarchyKeyCache();

        return $this->cache->del($keyString) >= 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache(CacheItemInterface $item, $ttl)
    {
        if ($ttl < 0) {
            return false;
        }

        $key  = $this->getHierarchyKey($item->getKey());
        $data = serialize([true, $item->get(), $item->getTags()]);

        if ($ttl === null || $ttl === 0) {
            return 'OK' === $this->cache->set($key, $data)->getPayload();
        }

        return 'OK' === $this->cache->setex($key, $ttl, $data)->getPayload();
    }

    /**
     * {@inheritdoc}
     */
    protected function getValueFormStore($key)
    {
        return $this->cache->get($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function appendListItem($name, $value)
    {
        $this->cache->lpush($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    protected function getList($name)
    {
        return $this->cache->lrange($name, 0, -1);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeList($name)
    {
        return $this->cache->del($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeListItem($name, $key)
    {
        return $this->cache->lrem($name, 0, $key);
    }
}
