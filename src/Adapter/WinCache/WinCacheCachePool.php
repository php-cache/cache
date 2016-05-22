<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\WinCache;

use Cache\Adapter\Common\AbstractCachePool;
use Psr\Cache\CacheItemInterface;

/**
 * @author Daniel Bannert <d.bannert@anolilab.de>
 */
class WinCacheCachePool extends AbstractCachePool
{
    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        $success = false;
        $data    = wincache_ucache_get($key, $success);

        return [$success, $data, []];
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllObjectsFromCache()
    {
        return wincache_ucache_clear('user');
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key)
    {
        wincache_ucache_delete($key);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache(CacheItemInterface $item, $ttl)
    {
        if ($ttl < 0) {
            return false;
        }

        return wincache_ucache_set($item->getKey(), $item->get(), $ttl);
    }
}
