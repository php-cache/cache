<?php

/*
 * This file is part of php-cache\apcu-adapter package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Apcu;

use Cache\Adapter\Common\AbstractCachePool;
use Psr\Cache\CacheItemInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ApcuCachePool extends AbstractCachePool
{
    protected function fetchObjectFromCache($key)
    {
        $success = false;
        $data    = apcu_fetch($key, $success);

        return [$success, $data];
    }

    protected function clearAllObjectsFromCache()
    {
        return apcu_clear_cache();
    }

    protected function clearOneObjectFromCache($key)
    {
        apcu_delete($key);

        return true;
    }

    protected function storeItemInCache($key, CacheItemInterface $item, $ttl)
    {
        return apcu_store($key, $item->get(), $ttl);
    }
}
