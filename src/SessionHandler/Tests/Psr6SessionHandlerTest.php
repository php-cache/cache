<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\SessionHandler\Tests;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\SessionHandler\Psr6SessionHandler;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Psr6SessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    const TTL    = 100;
    const PREFIX = 'pre';

    /**
     * @type Psr6SessionHandler
     */
    private $handler;

    /**
     * @type \PHPUnit_Framework_MockObject_MockObject|CacheItemPoolInterface
     */
    private $psr6;

    protected function setUp()
    {
        parent::setUp();
        $this->psr6 = $this->getMockBuilder(ArrayCachePool::class)
            ->setMethods(['getItem', 'deleteItem', 'save'])
            ->getMock();
        $this->handler = new Psr6SessionHandler($this->psr6, ['prefix' => self::PREFIX, 'ttl' => self::TTL]);
    }

    public function testOpen()
    {
        $this->assertTrue($this->handler->open('foo', 'bar'));
    }

    public function testClose()
    {
        $this->assertTrue($this->handler->close());
    }

    public function testGc()
    {
        $this->assertTrue($this->handler->gc(4711));
    }

    public function testReadMiss()
    {
        $item = $this->getItemMock();
        $item->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $this->psr6->expects($this->once())
            ->method('getItem')
            ->willReturn($item);
        $this->assertEquals('', $this->handler->read('foo'));
    }

    public function testReadHit()
    {
        $item = $this->getItemMock();
        $item->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $item->expects($this->once())
            ->method('get')
            ->willReturn('bar');
        $this->psr6->expects($this->once())
            ->method('getItem')
            ->willReturn($item);
        $this->assertEquals('bar', $this->handler->read('foo'));
    }

    public function testWrite()
    {
        $item = $this->getItemMock();
        $item->expects($this->once())
            ->method('set')
            ->with('session value')
            ->willReturnSelf();
        $item->expects($this->once())
            ->method('expiresAfter')
            ->with(self::TTL)
            ->willReturnSelf();
        $this->psr6->expects($this->once())
            ->method('getItem')
            ->with(self::PREFIX.'foo')
            ->willReturn($item);
        $this->psr6->expects($this->once())
            ->method('save')
            ->with($item)
            ->willReturn(true);
        $this->assertTrue($this->handler->write('foo', 'session value'));
    }

    public function testDestroy()
    {
        $this->psr6->expects($this->once())
            ->method('deleteItem')
            ->with(self::PREFIX.'foo')
            ->willReturn(true);
        $this->assertTrue($this->handler->destroy('foo'));
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getItemMock()
    {
        return $this->getMockBuilder(CacheItemInterface::class)
            ->setMethods(['isHit', 'getKey', 'get', 'set', 'expiresAt', 'expiresAfter'])
            ->getMock();
    }
}
