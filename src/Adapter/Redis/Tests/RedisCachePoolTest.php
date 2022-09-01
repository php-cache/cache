<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Redis\Tests;

use Cache\Adapter\Common\Exception\CachePoolException;
use Cache\Adapter\Redis\RedisCachePool;
use PHPUnit\Framework\TestCase;

class RedisCachePoolTest extends TestCase
{
    /**
     * Tests that an exception is thrown if invalid object is
     * passed to the constructor.
     */
    public function testConstructorWithInvalidObject()
    {
        $this->expectException(CachePoolException::class);

        new RedisCachePool(new \stdClass());
    }
}
