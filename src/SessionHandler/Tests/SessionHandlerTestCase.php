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

use PHPUnit\Framework\TestCase;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Daniel Bannert <d.bannert@anolilab.de>
 */
abstract class SessionHandlerTestCase extends TestCase
{
    public const TTL = 100;
    public const PREFIX = 'pre';

    protected \SessionHandlerInterface&\SessionUpdateTimestampHandlerInterface $handler;

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
        $this->assertSame(0, $this->handler->gc(4711));
    }
}
