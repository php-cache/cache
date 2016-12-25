<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2016 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Void;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Hierarchy\HierarchicalPoolInterface;
use Cache\Taggable\TaggablePoolInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class VoidCachePool extends AbstractCachePool implements TaggablePoolInterface, HierarchicalPoolInterface
{
    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        return [false, null, [], null];
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllObjectsFromCache()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache(PhpCacheItem $item, $ttl)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clearTags(array $tags)
    {
        return true;
    }
}
