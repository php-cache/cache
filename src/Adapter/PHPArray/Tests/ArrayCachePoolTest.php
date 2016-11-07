<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2016 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */


namespace Cache\Adapter\PHPArray\Tests;

use Cache\Adapter\PHPArray\ArrayCachePool;

class ArrayCachePoolTest extends \PHPUnit_Framework_TestCase
{
    public function testLimit()
    {
        $pool = new ArrayCachePool(2);
        $item = $pool->getItem('key1')->set('value1');
        $pool->save($item);

        $item = $pool->getItem('key2')->set('value2');
        $pool->save($item);

        // Both items should be in the pool, nothing strange yet
        $this->assertTrue($pool->hasItem('key1'));
        $this->assertTrue($pool->hasItem('key2'));

        $item = $pool->getItem('key3')->set('value3');
        $pool->save($item);

        // First item should be dropped
        $this->assertFalse($pool->hasItem('key1'));
        $this->assertTrue($pool->hasItem('key2'));
        $this->assertTrue($pool->hasItem('key3'));

        $this->assertFalse($pool->getItem('key1')->isHit());
        $this->assertTrue($pool->getItem('key2')->isHit());
        $this->assertTrue($pool->getItem('key3')->isHit());

        $item = $pool->getItem('key4')->set('value4');
        $pool->save($item);

        // Only the last two items should be in place
        $this->assertFalse($pool->hasItem('key1'));
        $this->assertFalse($pool->hasItem('key2'));
        $this->assertTrue($pool->hasItem('key3'));
        $this->assertTrue($pool->hasItem('key4'));
    }
}
