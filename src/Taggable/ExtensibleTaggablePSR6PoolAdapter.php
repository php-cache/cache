<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Taggable;

use Psr\Cache\CacheItemPoolInterface;

class ExtensibleTaggablePSR6PoolAdapter extends TaggablePSR6PoolAdapter
{
    private readonly CacheItemPoolInterface $extensionCachePool;

    private readonly CacheItemPoolInterface $extensionTagStorePool;

    protected function __construct(CacheItemPoolInterface $cachePool, ?CacheItemPoolInterface $tagStorePool = null)
    {
        parent::__construct($cachePool, $tagStorePool);

        $this->extensionCachePool = $cachePool;
        $this->extensionTagStorePool = $tagStorePool ?? $cachePool;
    }

    protected function getCachePool(): CacheItemPoolInterface
    {
        return $this->extensionCachePool;
    }

    protected function getTagStorePool(): CacheItemPoolInterface
    {
        return $this->extensionTagStorePool;
    }
}
