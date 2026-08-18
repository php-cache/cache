<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Util\Tests;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Util;
use PHPUnit\Framework\TestCase;

class SimpleCacheUtilTest extends TestCase
{
    public function testRememberReturnsCachedFalseyValues()
    {
        foreach ([false, 0, '', [], null] as $index => $value) {
            $cache = new ArrayCachePool();
            $key = 'key'.$index;
            $cache->set($key, $value);
            $created = false;

            $result = Util\SimpleCache\remember($cache, $key, null, static function () use (&$created): string {
                $created = true;

                return 'replacement';
            });

            self::assertSame($value, $result);
            self::assertFalse($created);
        }
    }

    public function testRememberCacheHit()
    {
        $cache = new ArrayCachePool();
        $cache->set('foo', 'bar');
        $res = Util\SimpleCache\remember($cache, 'foo', null, function () {
            throw new \Exception('bad');
        });
        $this->assertEquals('bar', $res);
    }

    public function testRememberCacheMiss()
    {
        $cache = new ArrayCachePool();
        $res = Util\SimpleCache\remember($cache, 'foo', null, function () {
            return 'bar';
        });
        $this->assertEquals('bar', $res);
        $this->assertEquals('bar', $cache->get('foo'));
    }
}
