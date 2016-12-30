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

use Cache\SessionHandler\Psr6SessionHandler;
use Mockery as m;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 */
class Psr6SessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @type Psr6SessionHandler
     */
    private $handler;

    /**
     * @type m\MockInterface|CacheItemPoolInterface
     */
    private $mock;

    /**
     * @type m\MockInterface|CacheItemInterface
     */
    private $itemMock;

    protected function setUp()
    {
        parent::setUp();

        $this->mock    = m::mock(CacheItemPoolInterface::class);
        $this->handler = new Psr6SessionHandler($this->mock);

        $this->itemMock = m::mock(CacheItemInterface::class);
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(Psr6SessionHandler::class, $this->handler);
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

    public function testRead()
    {
    }

    public function testWrite()
    {
    }

    public function testDestroy()
    {
    }
}
