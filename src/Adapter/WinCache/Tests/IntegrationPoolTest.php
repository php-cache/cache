<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\WinCache\Tests;

use Cache\Adapter\WinCache\WinCacheCachePool;
use Cache\IntegrationTests\CachePoolTest as BaseTest;

class IntegrationPoolTest extends BaseTest
{
    protected $skippedTests = [
        'testExpiration'      => 'The cache expire at the next request.',
        'testSaveExpired'     => 'The cache expire at the next request.',
        'testDeferredExpired' => 'The cache expire at the next request.',
    ];

    public function createCachePool()
    {
        if (defined('HHVM_VERSION') || !function_exists('wincache_ucache_clear')) {
            $this->markTestSkipped();
        }

        return new WinCacheCachePool();
    }
}
