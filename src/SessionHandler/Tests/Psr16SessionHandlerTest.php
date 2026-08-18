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

use Cache\SessionHandler\Psr16SessionHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\SimpleCache\CacheInterface;

/**
 * @author Daniel Bannert <d.bannert@anolilab.de>s
 */
class Psr16SessionHandlerTest extends SessionHandlerTestCase
{
    private CacheInterface&MockObject $psr16;

    private RecordingSessionLock $lock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->psr16 = $this->createMock(CacheInterface::class);
        $this->lock = new RecordingSessionLock();
        $this->handler = new Psr16SessionHandler($this->psr16, $this->lock, ['prefix' => self::PREFIX, 'ttl' => self::TTL]);
    }

    public function testReadMiss()
    {
        $this->psr16->expects($this->once())
            ->method('get')
            ->willReturn('');

        $this->assertEquals('', $this->handler->read('foo'));
    }

    public function testReadReturnsFalseWithoutReadingWhenLockCannotBeAcquired(): void
    {
        $lock = new RecordingSessionLock(false);
        $this->psr16->expects($this->never())
            ->method('get');

        $handler = new Psr16SessionHandler(
            $this->psr16,
            $lock,
            ['prefix' => self::PREFIX, 'ttl' => self::TTL]
        );

        $this->assertFalse($handler->read('foo'));
        $this->assertSame("acquire foo\n", $lock->events);
    }

    public function testValidateIdReturnsFalseWithoutReadingWhenLockCannotBeAcquired(): void
    {
        $lock = new RecordingSessionLock(false);
        $this->psr16->expects($this->never())
            ->method('get');
        $handler = new Psr16SessionHandler($this->psr16, $lock);

        $this->assertFalse($handler->validateId('foo'));
        $this->assertSame("acquire foo\n", $lock->events);
    }

    public function testReadHit()
    {
        $this->psr16->expects($this->once())
            ->method('get')
            ->with(self::PREFIX.'foo', '')
            ->willReturn('bar');

        $this->assertEquals('bar', $this->handler->read('foo'));
    }

    public function testReadTreatsNonStringValuesAsMisses(): void
    {
        $this->psr16->expects($this->once())
            ->method('get')
            ->willReturn(['not session data']);

        $this->assertSame('', $this->handler->read('foo'));
    }

    public function testLockIsHeldThroughWriteAndTimestampUpdateUntilClose(): void
    {
        $this->psr16->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls('before', 'after');
        $this->psr16->expects($this->exactly(2))
            ->method('set')
            ->with(self::PREFIX.'foo', 'after', self::TTL)
            ->willReturn(true);

        $this->assertSame('before', $this->handler->read('foo'));
        $this->assertTrue($this->handler->write('foo', 'after'));
        $this->assertTrue($this->handler->updateTimestamp('foo', 'after'));
        $this->assertSame("acquire foo\n", $this->lock->events);
        $this->assertTrue($this->handler->close());
        $this->assertSame("acquire foo\nrelease foo\n", $this->lock->events);
    }

    public function testSwitchingSessionsReleasesThePreviousLock(): void
    {
        $this->psr16->expects($this->exactly(2))
            ->method('get')
            ->willReturn('data');

        $this->assertSame('data', $this->handler->read('first'));
        $this->assertSame('data', $this->handler->read('second'));
        $this->assertSame(
            "acquire first\nrelease first\nacquire second\n",
            $this->lock->events
        );
    }

    public function testWritingARegeneratedSessionSwitchesLocks(): void
    {
        $this->psr16->expects($this->once())
            ->method('get')
            ->willReturn('before');
        $this->psr16->expects($this->once())
            ->method('set')
            ->with(self::PREFIX.'new', 'after', self::TTL)
            ->willReturn(true);

        $this->assertSame('before', $this->handler->read('old'));
        $this->assertTrue($this->handler->write('new', 'after'));
        $this->assertSame(
            "acquire old\nrelease old\nacquire new\n",
            $this->lock->events
        );
    }

    public function testWriteReleasesTheLockWhenStorageThrows(): void
    {
        $exception = new \RuntimeException('write failed');
        $this->psr16->expects($this->once())
            ->method('get')
            ->willReturn('before');
        $this->psr16->expects($this->once())
            ->method('set')
            ->willThrowException($exception);

        $this->assertSame('before', $this->handler->read('foo'));

        try {
            $this->handler->write('foo', 'after');
            $this->fail('The storage exception was not thrown.');
        } catch (\RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertSame("acquire foo\nrelease foo\n", $this->lock->events);
    }

    public function testWriteReturnsFalseWithoutStorageWhenLockCannotBeAcquired(): void
    {
        $lock = new RecordingSessionLock(false);
        $this->psr16->expects($this->never())
            ->method('set');
        $handler = new Psr16SessionHandler($this->psr16, $lock);

        $this->assertFalse($handler->write('foo', 'data'));
        $this->assertSame("acquire foo\n", $lock->events);
    }

    public function testTimestampUpdateReleasesTheLockWhenStorageThrows(): void
    {
        $exception = new \RuntimeException('timestamp update failed');
        $invocation = 0;
        $this->psr16->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function () use (&$invocation, $exception): string {
                if (1 === ++$invocation) {
                    return 'before';
                }

                throw $exception;
            });

        $this->assertSame('before', $this->handler->read('foo'));

        try {
            $this->handler->updateTimestamp('foo', 'before');
            $this->fail('The storage exception was not thrown.');
        } catch (\RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertSame("acquire foo\nrelease foo\n", $this->lock->events);
    }

    public function testTimestampUpdateReturnsFalseWithoutStorageWhenLockCannotBeAcquired(): void
    {
        $lock = new RecordingSessionLock(false);
        $this->psr16->expects($this->never())
            ->method('get');
        $handler = new Psr16SessionHandler($this->psr16, $lock);

        $this->assertFalse($handler->updateTimestamp('foo', 'data'));
        $this->assertSame("acquire foo\n", $lock->events);
    }

    public function testDestroyReleasesTheLock(): void
    {
        $this->psr16->expects($this->once())
            ->method('get')
            ->willReturn('before');
        $this->psr16->expects($this->once())
            ->method('delete')
            ->with(self::PREFIX.'foo')
            ->willReturn(true);

        $this->assertSame('before', $this->handler->read('foo'));
        $this->assertTrue($this->handler->destroy('foo'));
        $this->assertSame("acquire foo\nrelease foo\n", $this->lock->events);
    }

    public function testDestroyReleasesTheLockWhenStorageThrows(): void
    {
        $exception = new \RuntimeException('delete failed');
        $this->psr16->expects($this->once())
            ->method('get')
            ->willReturn('before');
        $this->psr16->expects($this->once())
            ->method('delete')
            ->willThrowException($exception);

        $this->assertSame('before', $this->handler->read('foo'));

        try {
            $this->handler->destroy('foo');
            $this->fail('The storage exception was not thrown.');
        } catch (\RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertSame("acquire foo\nrelease foo\n", $this->lock->events);
    }

    public function testDestroyReturnsFalseWithoutStorageWhenLockCannotBeAcquired(): void
    {
        $lock = new RecordingSessionLock(false);
        $this->psr16->expects($this->never())
            ->method('delete');
        $handler = new Psr16SessionHandler($this->psr16, $lock);

        $this->assertFalse($handler->destroy('foo'));
        $this->assertSame("acquire foo\n", $lock->events);
    }

    public function testEmptyNewSessionIsDiscardedWithoutCacheDelete(): void
    {
        $this->psr16->expects($this->once())
            ->method('get')
            ->willReturn('');
        $this->psr16->expects($this->never())
            ->method('delete');

        $this->assertSame('', $this->handler->read('foo'));
        $this->assertTrue($this->handler->write('foo', ''));
        $this->assertSame("acquire foo\nrelease foo\n", $this->lock->events);
    }

    public function testWrite()
    {
        $this->psr16->expects($this->once())
            ->method('set')
            ->with(self::PREFIX.'foo', 'session value', self::TTL)
            ->willReturn(true);

        $this->assertTrue($this->handler->write('foo', 'session value'));
    }

    public function testDestroy()
    {
        $this->psr16->expects($this->once())
            ->method('delete')
            ->with(self::PREFIX.'foo')
            ->willReturn(true);

        $this->assertTrue($this->handler->destroy('foo'));
    }

    #[DataProvider('getOptionFixtures')]
    public function testSupportedOptions(array $options, bool $supported): void
    {
        try {
            new Psr16SessionHandler($this->psr16, new RecordingSessionLock(), $options);

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
        $this->psr16->expects($this->once())
            ->method('get')
            ->with(self::PREFIX.'foo')
            ->willReturn('session value');
        $this->psr16->expects($this->once())
            ->method('set')
            ->with(self::PREFIX.'foo', 'session value', self::TTL)
            ->willReturn(true);

        $this->assertTrue($this->handler->updateTimestamp('foo', 'session value'));
    }

    public function testUpdateTimestampReturnsFalseForMissingSession(): void
    {
        $this->psr16->expects($this->once())
            ->method('get')
            ->with(self::PREFIX.'foo')
            ->willReturn(null);
        $this->psr16->expects($this->never())
            ->method('set');

        $this->assertFalse($this->handler->updateTimestamp('foo', 'session value'));
    }

    public function testValidateId()
    {
        $this->psr16->expects($this->once())
            ->method('get')
            ->with(self::PREFIX.'foo', '')
            ->willReturn('bar');

        $this->assertTrue($this->handler->validateId('foo'));
    }
}
