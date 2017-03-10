<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Laravel;

use Cache\Adapter\Common\AbstractCachePool;
use Illuminate\Contracts\Cache\Store;
use Cache\Adapter\Common\PhpCacheItem;

/**
 * @author Florian Voutzinos <florian@voutzinos.com>
 * @author Daniel Bannert <d.bannert@anolilab.de>
 */
class LaravelCacheAdapter extends AbstractCachePool
{
    /**
     * This value replaces NULL values that are considered
     * unexisting by Laravel.
     *
     * @cosnt
     */
    const NULL_VALUE = '__LARAVEL_NULL__';

    /**
     * A laravel cache store instance.
     *
     * @var \Illuminate\Contracts\Cache\Store
     */
    private $store;

    /**
     * Constructor.
     *
     * @param \Illuminate\Contracts\Cache\Store $store
     */
    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    /**
     * @return \Illuminate\Contracts\Cache\Store
     */
    public function getCache()
    {
        return $this->store;
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache(PhpCacheItem $item, $ttl)
    {
        if ($ttl < 0) {
            return false;
        }

        $ttl = null === $ttl ? 0 : $ttl / 60;

        if (null === $value = $item->get()) {
            $value = self::NULL_VALUE;
        }

        $data = serialize([$value, $item->getTags(), $item->getExpirationTimestamp()]);

        $this->store->put($item->getKey(), $data, $ttl);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        $cacheData = $this->store->get($key);
        $success = null !== $cacheData;

        if (!$success) {
            return [false, null, [], null];
        }

        list($data, $tags, $timestamp) = unserialize($cacheData);

        if (self::NULL_VALUE === $data) {
            $data = null;
        }

        return [$success, $data, $tags, $timestamp];
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllObjectsFromCache()
    {
        $this->store->flush();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key)
    {
        if (null === $this->store->get($key)) {
            return true;
        }

        return $this->store->forget($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function getList($name)
    {
        if (false === $list = $this->store->get($name)) {
            return [];
        }

        return (array) $list;
    }

    /**
     * {@inheritdoc}
     */
    protected function removeList($name)
    {
        return $this->store->forget($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function appendListItem($name, $key)
    {
        $list   = $this->getList($name);
        $list[] = $key;

        $this->store->put($name, $list, 0);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeListItem($name, $key)
    {
        $list = $this->getList($name);

        foreach ($list as $i => $item) {
            if ($item === $key) {
                unset($list[$i]);
            }
        }

        $this->store->put($name, $list, 0);
    }
}
