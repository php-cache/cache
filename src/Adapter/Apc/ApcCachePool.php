<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Apc;

use Cache\Adapter\Common\AbstractCachePool;
use Psr\Cache\CacheItemInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ApcCachePool extends AbstractCachePool
{
    protected function fetchObjectFromCache($key)
    {
        $success = false;
        $data    = apc_fetch($key, $success);

        return [$success, $data, []];
    }

    protected function clearAllObjectsFromCache()
    {
        return apc_clear_cache('user');
    }

    protected function clearOneObjectFromCache($key)
    {
        apc_delete($key);

        return true;
    }

    protected function storeItemInCache(CacheItemInterface $item, $ttl)
    {
        if ($ttl < 0) {
            return false;
        }

        return apc_store($item->getKey(), $item->get(), $ttl);
    }
}
