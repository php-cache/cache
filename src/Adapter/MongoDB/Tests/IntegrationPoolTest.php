<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2016 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */


namespace Cache\Adapter\MongoDB\Tests;

use Cache\Adapter\MongoDB\MongoDBCachePool;
use Cache\IntegrationTests\CachePoolTest;

class IntegrationPoolTest extends CachePoolTest
{
    use CreateServerTrait;

    public function createCachePool()
    {
        return new MongoDBCachePool($this->getCollection());
    }
}
