<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Chain\Tests;

use Cache\Adapter\Chain\CachePoolChain;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Adapter\Predis\PredisCachePool;
use Cache\IntegrationTests\CachePoolTest;
use Predis\Client;

class IntegrationPoolTest extends CachePoolTest
{
    private $adapters;

    public function createCachePool()
    {
        return new CachePoolChain($this->getAdapters());
    }

    /**
     * @return mixed
     */
    public function getAdapters()
    {
        if ($this->adapters === null) {
            $this->adapters = [new PredisCachePool(new Client('tcp:/127.0.0.1:6379')), new ArrayCachePool()];
        }

        return $this->adapters;
    }
}
