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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Daniel Bannert <d.bannert@anolilab.de>s
 */
class Psr6SessionHandlerTest extends SessionHandlerTestCase
{
    private CacheItemPoolInterface&MockObject $psr6;

    private RecordingSessionLock $lock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->psr6 = $this->createMock(CacheItemPoolInterface::class);
        $this->lock = new RecordingSessionLock();
        $this->handler = new Psr6SessionHandler($this->psr6, $this->lock, ['prefix' => self::PREFIX, 'ttl' => self::TTL]);
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

    public function testReadReturnsFalseWithoutReadingWhenLockCannotBeAcquired(): void
    {
        $lock = new RecordingSessionLock(false);
        $this->psr6->expects($this->never())
            ->method('getItem');

        $handler = new Psr6SessionHandler(
            $this->psr6,
            $lock,
            ['prefix' => self::PREFIX, 'ttl' => self::TTL]
        );

        $this->assertFalse($handler->read('foo'));
        $this->assertSame(1, $lock->acquireCalls);
        $this->assertSame('foo', $lock->acquiredSessionId);
        $this->assertSame(0, $lock->releaseCalls);
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

    public function testReadTreatsNonStringValuesAsMisses(): void
    {
        $item = $this->getItemMock();
        $item->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $item->expects($this->once())
            ->method('get')
            ->willReturn(['not session data']);
        $this->psr6->expects($this->once())
            ->method('getItem')
            ->willReturn($item);

        $this->assertSame('', $this->handler->read('foo'));
    }

    public function testValidateAndReadShareTheLockUntilClose(): void
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

        $this->assertTrue($this->handler->validateId('foo'));
        $this->assertSame('bar', $this->handler->read('foo'));
        $this->assertSame("acquire foo\n", $this->lock->events);
        $this->assertTrue($this->handler->close());
        $this->assertSame("acquire foo\nrelease foo\n", $this->lock->events);
    }

    public function testReadReleasesTheLockWhenStorageThrows(): void
    {
        $exception = new \RuntimeException('read failed');
        $this->psr6->expects($this->once())
            ->method('getItem')
            ->willThrowException($exception);

        try {
            $this->handler->read('foo');
            $this->fail('The storage exception was not thrown.');
        } catch (\RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertSame("acquire foo\nrelease foo\n", $this->lock->events);
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

    #[DataProvider('getOptionFixtures')]
    public function testSupportedOptions(array $options, bool $supported): void
    {
        try {
            new Psr6SessionHandler($this->psr6, new RecordingSessionLock(), $options);

            $this->assertTrue($supported);
        } catch (\InvalidArgumentException $e) {
            $this->assertFalse($supported);
        }
    }

    public static function getOptionFixtures(): array
    {
        return [
            [['prefix' => 'session'], true],
            [['ttl' => 100], true],
            [['prefix' => 'session', 'ttl' => 200], true],
            [['ttl' => 100, 'foo' => 'bar'], false],
        ];
    }

    public function testUpdateTimestamp()
    {
        $item = $this->getItemMock();
        $item->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $item->expects($this->once())
            ->method('set')
            ->with('session value')
            ->willReturnSelf();
        $item->expects($this->exactly(2))
            ->method('expiresAfter')
            ->with(self::TTL)
            ->willReturnSelf();
        $this->psr6->expects($this->exactly(2))
            ->method('getItem')
            ->with(self::PREFIX.'foo')
            ->willReturn($item);
        $this->psr6->expects($this->exactly(2))
            ->method('save')
            ->with($item)
            ->willReturn(true);

        $this->handler->write('foo', 'session value');

        $this->assertTrue($this->handler->updateTimestamp('foo', 'session value'));
    }

    public function testUpdateTimestampDoesNotCreateAMissingSession(): void
    {
        $item = $this->getItemMock();
        $item->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $item->expects($this->never())->method('expiresAfter');
        $this->psr6->expects($this->once())
            ->method('getItem')
            ->with(self::PREFIX.'missing')
            ->willReturn($item);
        $this->psr6->expects($this->never())->method('save');

        $this->assertFalse($this->handler->updateTimestamp('missing', 'session value'));
    }

    public function testValidateId()
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

        $this->assertTrue($this->handler->validateId('foo'));
    }

    private function getItemMock(): CacheItemInterface&MockObject
    {
        return $this->createMock(CacheItemInterface::class);
    }
}
