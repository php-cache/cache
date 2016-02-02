<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Void;

use Cache\Adapter\Common\AbstractCachePool;
use Psr\Cache\CacheItemInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class VoidCachePool extends AbstractCachePool
{
    protected function fetchObjectFromCache($key)
    {
        return [false, null];
    }

    protected function clearAllObjectsFromCache()
    {
        return true;
    }

    protected function clearOneObjectFromCache($key)
    {
        return true;
    }

    protected function storeItemInCache($key, CacheItemInterface $item, $ttl)
    {
        return true;
    }
}
