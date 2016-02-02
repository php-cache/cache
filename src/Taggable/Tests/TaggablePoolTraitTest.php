<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Taggable\Tests;

use Cache\Taggable\Tests\Helper\CachePool;

class TaggablePoolTraitTest extends \PHPUnit_Framework_TestCase
{
    public function testGenerateCacheKey()
    {
        $cache    = new CachePool();
        $inputKey = 'foobar';
        $tags     = ['bar', 'biz'];

        $key1 = $cache->exposeGenerateCacheKey($inputKey, $tags);
        $key2 = $cache->exposeGenerateCacheKey($inputKey, $tags);
        $this->assertTrue($key1 === $key2, 'Same input should generate same cache keys');

        $key1 = $cache->exposeGenerateCacheKey($inputKey, ['abc', '123']);
        $key2 = $cache->exposeGenerateCacheKey($inputKey, ['123', 'abc']);
        $this->assertTrue($key1 === $key2, 'Order should not matter when generating cache keys');

        $key1 = $cache->exposeGenerateCacheKey($inputKey, []);
        $this->assertTrue($inputKey === $key1, 'Key should not be altered if no tags used. ');
    }

    public function testFlushTag()
    {
        $cache = new CachePool();
        $item  = $cache->getItem('foo', ['tag']);
        $item->set('bar');
        $cache->save($item);

        $this->assertTrue($cache->getItem('foo', ['tag'])->isHit());

        // Test remove the tag
        $cache->exposeFlushTag('tag');
        $this->assertFalse($cache->getItem('foo', ['tag'])->isHit());
    }

    public function testGetTagIdHit()
    {
        $expected = 'value';
        $method   = new \ReflectionMethod('Cache\Taggable\Tests\Helper\CachePool', 'getTagId');
        $method->setAccessible(true);

        $item = $this->getMockBuilder('Cache\Taggable\Tests\Helper\CacheItem')
            ->setMethods(['isHit', 'get'])
            ->disableOriginalConstructor()
            ->getMock();
        $item->expects($this->once())->method('isHit')->willReturn(true);
        $item->expects($this->once())->method('get')->willReturn($expected);

        $cache = $this->getMockBuilder('Cache\Taggable\Tests\Helper\CachePool')
            ->setMethods(['validateTagName', 'getItemWithoutGenerateCacheKey'])
            ->disableOriginalConstructor()
            ->getMock();
        $cache->expects($this->once())->method('validateTagName')->willReturn(null);
        $cache->expects($this->once())->method('getItemWithoutGenerateCacheKey')->willReturn($item);

        $result = $method->invoke($cache, 'name');
        $this->assertEquals($expected, $result);
    }

    public function testGetTagIdMiss()
    {
        $method = new \ReflectionMethod('Cache\Taggable\Tests\Helper\CachePool', 'getTagId');
        $method->setAccessible(true);

        $item = $this->getMockBuilder('Cache\Taggable\Tests\Helper\CacheItem')
            ->setMethods(['isHit'])
            ->disableOriginalConstructor()
            ->getMock();
        $item->expects($this->once())->method('isHit')->willReturn(false);

        $cache = $this->getMockBuilder('Cache\Taggable\Tests\Helper\CachePool')
            ->setMethods(['validateTagName', 'getItemWithoutGenerateCacheKey', 'save'])
            ->disableOriginalConstructor()
            ->getMock();
        $cache->expects($this->once())->method('validateTagName')->willReturn(null);
        $cache->expects($this->once())->method('getItemWithoutGenerateCacheKey')->willReturn($item);
        $cache->expects($this->once())->method('save')->willReturn(true);

        $result = $method->invoke($cache, 'name');
        $this->assertRegExp('|^[0-9a-f]{15,25}$|', $result);
    }
}
