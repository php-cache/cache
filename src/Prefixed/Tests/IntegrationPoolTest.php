<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Prefixed\Tests;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\IntegrationTests\CachePoolTest;
use Cache\Prefixed\PrefixedCachePool;
use Psr\Cache\CacheItemPoolInterface;

class IntegrationPoolTest extends CachePoolTest
{
    /** @var array<array-key, mixed> */
    private array $storage = [];

    public function createCachePool(): CacheItemPoolInterface
    {
        return new PrefixedCachePool(new ArrayCachePool(null, $this->storage), 'prefix.');
    }
}
