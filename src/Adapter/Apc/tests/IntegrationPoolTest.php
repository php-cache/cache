<?php

/*
 * This file is part of php-cache\apc-adapter package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Apc\Tests;

use Cache\Adapter\Apc\ApcCachePool;
use Cache\IntegrationTests\CachePoolTest as BaseTest;

class IntegrationPoolTest extends BaseTest
{
    protected $skippedTests = [
        'testExpiration' => 'The cache expire at the next request.',
    ];

    public function createCachePool()
    {
        return new ApcCachePool();
    }
}
