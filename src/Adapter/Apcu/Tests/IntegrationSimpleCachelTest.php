<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Apcu\Tests;

use Cache\Adapter\Apcu\ApcuCachePool;
use Cache\IntegrationTests\SimpleCacheTest as BaseTest;
use Psr\SimpleCache\CacheInterface;

class IntegrationSimpleCachelTest extends BaseTest
{
    public function createSimpleCache(): CacheInterface
    {
        if (defined('HHVM_VERSION') || !function_exists('apcu_store') || (function_exists('apcu_enabled') && !apcu_enabled())) {
            $this->markTestSkipped();
        }

        return new ApcuCachePool();
    }
}
