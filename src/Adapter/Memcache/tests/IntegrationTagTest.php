<?php

/*
 * This file is part of php-cache\memcache-adapter package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Memcache\Tests;

use Cache\Adapter\Memcache\MemcacheCachePool;
use Cache\IntegrationTests\TaggableCachePoolTest;
use Memcache;

class IntegrationTagTest extends TaggableCachePoolTest
{
    private $client;

    public function createCachePool()
    {
        if (!class_exists('Memcache')) {
            $this->markTestSkipped();
        }

        return new MemcacheCachePool($this->getClient());
    }

    private function getClient()
    {
        if ($this->client === null) {
            $this->client = new Memcache();
            $this->client->connect('localhost', 11211);
        }

        return $this->client;
    }
}
