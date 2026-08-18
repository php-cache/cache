<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Bridge\SimpleCache\Tests;

use Cache\Adapter\Common\Exception\InvalidArgumentException as PoolInvalidArgumentException;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Bridge\SimpleCache\Exception\InvalidArgumentException as BridgeInvalidArgumentException;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;
use Cache\Namespaced\NamespacedCachePool;
use Cache\Prefixed\PrefixedCachePool;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class SimpleCacheBridgeTest extends TestCase
{
    /**
     * @var SimpleCacheBridge
     */
    private $bridge;

    /**
     * @var m\MockInterface|CacheItemPoolInterface
     */
    private $mock;

    /**
     * @var m\MockInterface|CacheItemInterface
     */
    private $itemMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock = m::mock(CacheItemPoolInterface::class);

        $this->bridge = new SimpleCacheBridge($this->mock);

        $this->itemMock = m::mock(CacheItemInterface::class);
    }

    public function testPoolInvalidArgumentsAreWrapped()
    {
        foreach ([
            'get' => 'getItem',
            'set' => 'getItem',
            'delete' => 'deleteItem',
            'getMultiple' => 'getItems',
            'setMultiple' => 'getItems',
            'deleteMultiple' => 'deleteItems',
            'has' => 'hasItem',
        ] as $operation => $poolOperation) {
            $exception = new PoolInvalidArgumentException('invalid', 42);
            $pool = $this->createMock(CacheItemPoolInterface::class);
            $pool->method($poolOperation)->willThrowException($exception);
            $bridge = new SimpleCacheBridge($pool);

            try {
                match ($operation) {
                    'get' => $bridge->get('key'),
                    'set' => $bridge->set('key', 'value'),
                    'delete' => $bridge->delete('key'),
                    'getMultiple' => $bridge->getMultiple(['key']),
                    'setMultiple' => $bridge->setMultiple(['key' => 'value']),
                    'deleteMultiple' => $bridge->deleteMultiple(['key']),
                    'has' => $bridge->has('key'),
                };

                self::fail(sprintf('%s did not wrap the pool exception', $operation));
            } catch (BridgeInvalidArgumentException $wrapped) {
                self::assertSame('invalid', $wrapped->getMessage());
                self::assertSame(42, $wrapped->getCode());
                self::assertSame($exception, $wrapped->getPrevious());
            }
        }
    }

    public function testSetMultipleWrapsItemInvalidArgument()
    {
        $exception = new PoolInvalidArgumentException('invalid');
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('getKey')->willReturn('key');
        $item->method('set')->with('value')->willReturnSelf();
        $item->method('expiresAfter')->with(null)->willThrowException($exception);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItems')->with(['key'])->willReturn(['key' => $item]);

        try {
            (new SimpleCacheBridge($pool))->setMultiple(['key' => 'value']);
            self::fail('setMultiple did not wrap the item exception');
        } catch (BridgeInvalidArgumentException $wrapped) {
            self::assertSame($exception, $wrapped->getPrevious());
        }
    }

    public function testGetMultipleWrapsLazyPoolInvalidArgument()
    {
        $exception = new class('invalid') extends \RuntimeException implements \Psr\Cache\InvalidArgumentException {
        };
        $items = (static function () use ($exception): \Generator {
            throw $exception;
            yield;
        })();
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItems')->willReturn($items);

        try {
            iterator_to_array((new SimpleCacheBridge($pool))->getMultiple(['key']));
            self::fail('getMultiple did not wrap the lazy pool exception');
        } catch (BridgeInvalidArgumentException $wrapped) {
            self::assertSame($exception, $wrapped->getPrevious());
        }
    }

    public function testSetMultipleWrapsLazyPoolInvalidArgument()
    {
        $exception = new class('invalid') extends \RuntimeException implements \Psr\Cache\InvalidArgumentException {
        };
        $items = (static function () use ($exception): \Generator {
            throw $exception;
            yield;
        })();
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItems')->willReturn($items);

        try {
            (new SimpleCacheBridge($pool))->setMultiple(['key' => 'value']);
            self::fail('setMultiple did not wrap the lazy pool exception');
        } catch (BridgeInvalidArgumentException $wrapped) {
            self::assertSame($exception, $wrapped->getPrevious());
        }
    }

    public function testSetMultipleDoesNotMutateBeforeLazyIterationCompletes()
    {
        $exception = new class('invalid') extends \RuntimeException implements \Psr\Cache\InvalidArgumentException {
        };
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('getKey')->willReturn('first');
        $item->method('set')->willReturnSelf();
        $item->method('expiresAfter')->willReturnSelf();
        $items = (static function () use ($exception, $item): \Generator {
            yield 'first' => $item;
            throw $exception;
        })();
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItems')->willReturn($items);
        $pool->expects(self::never())->method('saveDeferred');

        try {
            (new SimpleCacheBridge($pool))->setMultiple(['first' => 'value', 'second' => 'value']);
            self::fail('setMultiple did not wrap the lazy pool exception');
        } catch (BridgeInvalidArgumentException $wrapped) {
            self::assertSame($exception, $wrapped->getPrevious());
        }
    }

    public function testSetMultipleAttemptsEveryItemAndAlwaysCommits()
    {
        $first = $this->createMock(CacheItemInterface::class);
        $first->method('getKey')->willReturn('first');
        $first->method('set')->with('one')->willReturnSelf();
        $first->method('expiresAfter')->with(null)->willReturnSelf();
        $second = $this->createMock(CacheItemInterface::class);
        $second->method('getKey')->willReturn('second');
        $second->method('set')->with('two')->willReturnSelf();
        $second->method('expiresAfter')->with(null)->willReturnSelf();

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItems')->willReturn(['first' => $first, 'second' => $second]);
        $pool->expects(self::exactly(2))->method('saveDeferred')->willReturnOnConsecutiveCalls(false, true);
        $pool->expects(self::once())->method('commit')->willReturn(true);

        self::assertFalse((new SimpleCacheBridge($pool))->setMultiple(['first' => 'one', 'second' => 'two']));
    }

    public function testClearReturnsPoolResult()
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects(self::once())->method('clear')->willReturn(true);

        self::assertTrue((new SimpleCacheBridge($pool))->clear());
    }

    public function testGetMultipleUsesDefaultForMisses()
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('getKey')->willReturn('key');
        $item->method('isHit')->willReturn(false);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItems')->with(['key'])->willReturn(['key' => $item]);

        self::assertSame(
            ['key' => 'default'],
            iterator_to_array((new SimpleCacheBridge($pool))->getMultiple(['key'], 'default'))
        );
    }

    public function testGetMultiplePreservesNumericStringKeys()
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('getKey')->willReturn('123');
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn('value');

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItems')->with(['123'])->willReturn([123 => $item]);

        foreach ((new SimpleCacheBridge($pool))->getMultiple(['123']) as $key => $value) {
            self::assertSame('123', $key);
            self::assertSame('value', $value);
        }
    }

    public function testSetMultipleConvertsIntegerKeys()
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('getKey')->willReturn('1');
        $item->method('set')->with('value')->willReturnSelf();
        $item->method('expiresAfter')->with(null)->willReturnSelf();

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItems')->with(['1'])->willReturn(['1' => $item]);
        $pool->method('saveDeferred')->with($item)->willReturn(true);
        $pool->method('commit')->willReturn(true);

        self::assertTrue((new SimpleCacheBridge($pool))->setMultiple([1 => 'value']));
    }

    public function testMultipleOperationsRejectNonStringKeys()
    {
        $bridge = new SimpleCacheBridge($this->createStub(CacheItemPoolInterface::class));

        foreach (['getMultiple', 'deleteMultiple'] as $operation) {
            try {
                $bridge->{$operation}([1]);
                self::fail(sprintf('%s accepted an integer key', $operation));
            } catch (BridgeInvalidArgumentException $exception) {
                self::assertSame('Cache key must be string, "int" given', $exception->getMessage());
            }
        }
    }

    public function testOperationsRejectInvalidKeys()
    {
        $bridge = new SimpleCacheBridge($this->createStub(CacheItemPoolInterface::class));

        foreach (['', 'invalid/key'] as $key) {
            try {
                $bridge->has($key);
                self::fail(sprintf('has accepted the invalid key "%s"', $key));
            } catch (BridgeInvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(SimpleCacheBridge::class, $this->bridge);
    }

    public function testFetch()
    {
        $this->itemMock->shouldReceive('isHit')->times(1)->andReturn(true);
        $this->itemMock->shouldReceive('get')->times(1)->andReturn('some_value');

        $this->mock->shouldReceive('getItem')->withArgs(['some_item'])->andReturn($this->itemMock);

        $this->assertEquals('some_value', $this->bridge->get('some_item'));
    }

    public function testFetchMiss()
    {
        $this->itemMock->shouldReceive('isHit')->times(1)->andReturn(false);

        $this->mock->shouldReceive('getItem')->withArgs(['no_item'])->andReturn($this->itemMock);

        $this->assertFalse($this->bridge->get('no_item', false));
    }

    public function testContains()
    {
        $this->mock->shouldReceive('hasItem')->withArgs(['no_item'])->andReturn(false);
        $this->mock->shouldReceive('hasItem')->withArgs(['some_item'])->andReturn(true);

        $this->assertFalse($this->bridge->has('no_item'));
        $this->assertTrue($this->bridge->has('some_item'));
    }

    public function testSave()
    {
        $this->itemMock->shouldReceive('set')->twice()->with('dummy_data');
        $this->itemMock->shouldReceive('expiresAfter')->once()->with(null);
        $this->itemMock->shouldReceive('expiresAfter')->once()->with(2);
        $this->mock->shouldReceive('getItem')->twice()->with('some_item')->andReturn($this->itemMock);
        $this->mock->shouldReceive('save')->twice()->with($this->itemMock)->andReturn(true);

        $this->assertTrue($this->bridge->set('some_item', 'dummy_data'));
        $this->assertTrue($this->bridge->set('some_item', 'dummy_data', 2));
    }

    public function testDelete()
    {
        $this->mock->shouldReceive('deleteItem')->once()->with('some_item')->andReturn(true);

        $this->assertTrue($this->bridge->delete('some_item'));
    }

    public function testSetMultiplePreservesKeysWithNamespacedPool()
    {
        $values = ['key1' => 'value1', 'key2' => 'value2'];
        $cache = new SimpleCacheBridge(new NamespacedCachePool(new ArrayCachePool(), 'namespace'));

        $this->assertTrue($cache->setMultiple($values));
        $this->assertSame($values, iterator_to_array($cache->getMultiple(array_keys($values))));
    }

    public function testSetMultiplePreservesKeysWithPrefixedPool()
    {
        $values = ['key1' => 'value1', 'key2' => 'value2'];
        $cache = new SimpleCacheBridge(new PrefixedCachePool(new ArrayCachePool(), 'prefix'));

        $this->assertTrue($cache->setMultiple($values));
        $this->assertSame($values, iterator_to_array($cache->getMultiple(array_keys($values))));
    }
}
