<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Memcached\Tests;

use Cache\Adapter\Common\CacheItem;
use Cache\Adapter\Memcached\MemcachedCachePool;

class MemcachedCachePoolTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Ensures that TTL larger than 30 days are sent as absolute timestamps
     * https://github.com/memcached/memcached/wiki/Programming#expiration.
     */
    public function testTimeToLiveMoreThan30days()
    {
        /** @type \Memcached|\PHPUnit_Framework_MockObject_MockObject $cache */
        $cache = $this->getMockBuilder(\Memcached::class)->disableOriginalConstructor()->getMock();
        $cache->expects($this->once())->method('set')->with(
            $this->equalTo('a'),
            $this->anything(),
            // Test that the TTL timestamp is absolute (greaterThanOrEqual to account for small timing)
            $this->greaterThanOrEqual(time() + (86400 * 365))
        )->willReturn(true);

        $pool = new MemcachedCachePool($cache);

        $cache_item = new CacheItem('a', false, '1');

        // Input relative expiration time of 1 year
        $cache_item->expiresAfter(86400 * 365);
        $pool->save($cache_item);
    }

    /**
     * Ensures that TTL less than 30 days are still sent as relative timestamps.
     */
    public function testTimeToLiveLessThan30days()
    {
        /** @type \Memcached|\PHPUnit_Framework_MockObject_MockObject $cache */
        $cache = $this->getMockBuilder(\Memcached::class)->disableOriginalConstructor()->getMock();
        $cache->expects($this->once())->method('set')->with(
            $this->equalTo('b'),
            $this->anything(),
            // Test that the TTL timestamp is relative
            $this->equalTo(86400 * 20)
        )->willReturn(true);

        $pool = new MemcachedCachePool($cache);

        $cache_item = new CacheItem('b', false, '1');

        // Input relative expiration time of 20 days
        $cache_item->expiresAfter(86400 * 20);
        $pool->save($cache_item);
    }
}
