<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Common\Tests;

use Cache\Adapter\Common\CacheItem;
use Psr\Cache\CacheItemInterface;

class CacheItemTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $item = new CacheItem('test_key');

        $this->assertInstanceOf(CacheItem::class, $item);
        $this->assertInstanceOf(CacheItemInterface::class, $item);
    }

    public function testGetKey()
    {
        $item = new CacheItem('test_key');
        $this->assertEquals('test_key', $item->getKey());
    }

    public function testSet()
    {
        $item = new CacheItem('test_key');

        $ref       = new \ReflectionObject($item);
        $valueProp = $ref->getProperty('value');
        $valueProp->setAccessible(true);
        $hasValueProp = $ref->getProperty('hasValue');
        $hasValueProp->setAccessible(true);

        $this->assertEquals(null, $valueProp->getValue($item));
        $this->assertFalse($hasValueProp->getValue($item));

        $item->set('value');

        $this->assertEquals('value', $valueProp->getValue($item));
        $this->assertTrue($hasValueProp->getValue($item));
    }

    public function testGet()
    {
        $item = new CacheItem('test_key');
        $this->assertNull($item->get());

        $item->set('test');
        $this->assertEquals('test', $item->get());
    }

    public function testHit()
    {
        $item = new CacheItem('test_key', true, 'value');
        $this->assertTrue($item->isHit());

        $item = new CacheItem('test_key', false, 'value');
        $this->assertFalse($item->isHit());

        $closure = function () {
            return [true, 'value', []];
        };
        $item = new CacheItem('test_key', $closure);
        $this->assertTrue($item->isHit());

        $closure = function () {
            return [false, null, []];
        };
        $item = new CacheItem('test_key', $closure);
        $this->assertFalse($item->isHit());
    }

    public function testGetExpirationDate()
    {
        $item = new CacheItem('test_key');

        $this->assertNull($item->getExpirationDate());

        $date = new \DateTime();

        $ref  = new \ReflectionObject($item);
        $prop = $ref->getProperty('expirationDate');
        $prop->setAccessible(true);
        $prop->setValue($item, $date);

        $this->assertEquals($date, $item->getExpirationDate());
    }

    public function testExpiresAt()
    {
        $item = new CacheItem('test_key');

        $this->assertNull($item->getExpirationDate());

        $item->expiresAt(new \DateTime('+1 second'));

        $this->assertEquals(new \DateTime('+1 second'), $item->getExpirationDate());
    }

    public function testExpiresAfter()
    {
        $item = new CacheItem('test_key');

        $this->assertNull($item->getExpirationDate());

        $item->expiresAfter(null);
        $this->assertNull($this->getExpectedException());

        $item->expiresAfter(new \DateInterval('PT1S'));
        $this->assertEquals(new \DateTime('+1 second'), $item->getExpirationDate());

        $item->expiresAfter(1);
        $this->assertEquals(new \DateTime('+1 second'), $item->getExpirationDate());
    }
}
