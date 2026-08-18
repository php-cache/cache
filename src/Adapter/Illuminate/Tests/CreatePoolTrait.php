<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Illuminate\Tests;

use Cache\Adapter\Illuminate\IlluminateCachePool;
use Illuminate\Cache\ArrayStore;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;

trait CreatePoolTrait
{
    private ?ArrayStore $illuminateStore = null;

    public function createCachePool(): CacheItemPoolInterface
    {
        return new IlluminateCachePool($this->getIlluminateStore());
    }

    private function getIlluminateStore(): ArrayStore
    {
        if (null === $this->illuminateStore) {
            $this->illuminateStore = new ArrayStore();
        }

        return $this->illuminateStore;
    }

    public function createSimpleCache(): CacheInterface
    {
        return $this->createCachePool();
    }
}
