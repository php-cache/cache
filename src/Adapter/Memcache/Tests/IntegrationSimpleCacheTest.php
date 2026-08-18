<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Memcache\Tests;

use Cache\Adapter\Memcache\MemcacheCachePool;
use Cache\IntegrationTests\SimpleCacheTest;

class IntegrationSimpleCacheTest extends SimpleCacheTest
{
    private ?\Memcache $client = null;

    public function createSimpleCache(): MemcacheCachePool
    {
        if (!class_exists('Memcache')) {
            $this->markTestSkipped();
        }

        return new MemcacheCachePool($this->getClient());
    }

    private function getClient(): \Memcache
    {
        if (null === $this->client) {
            $this->client = new \Memcache();
            $this->client->connect('localhost', 11211);
        }

        return $this->client;
    }
}
