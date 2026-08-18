<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Chain\Tests;

use Cache\Adapter\Chain\CachePoolChain;
use Cache\Adapter\Common\CacheItem;
use Cache\Adapter\Common\Exception\CachePoolException;
use Cache\Adapter\Common\PhpCachePool;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\TagInterop\TaggableCacheItemPoolInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Class ChainPoolTest.
 */
class CachePoolChainTest extends TestCase
{
    public function testRejectsPoolsThatCannotTransferPhpCacheItems()
    {
        $pool = $this->createMock(TaggableCacheItemPoolInterface::class);

        $this->expectException(\InvalidArgumentException::class);

        new CachePoolChain([$pool]);
    }

    public function testProvidesPsr16AccessDirectly()
    {
        $cache = new CachePoolChain([new ArrayCachePool(), new ArrayCachePool()]);

        self::assertInstanceOf(CacheInterface::class, $cache);
        self::assertTrue($cache->set('key', 'value'));
        self::assertSame('value', $cache->get('key'));
    }

    public function testSaveRejectsGenericItemsBeforeWritingToAnyPool()
    {
        $pool = $this->createMock(PhpCachePool::class);
        $pool->expects(self::never())->method('save');
        $item = $this->createMock(CacheItemInterface::class);

        $this->expectException(\Psr\Cache\InvalidArgumentException::class);

        (new CachePoolChain([$pool]))->save($item);
    }

    public function testSaveDeferredRejectsGenericItemsBeforeWritingToAnyPool()
    {
        $pool = $this->createMock(PhpCachePool::class);
        $pool->expects(self::never())->method('saveDeferred');
        $item = $this->createMock(CacheItemInterface::class);

        $this->expectException(\Psr\Cache\InvalidArgumentException::class);

        (new CachePoolChain([$pool]))->saveDeferred($item);
    }

    public function testPoolFailureIsRethrownByDefault()
    {
        $exception = new CachePoolException('failed');
        $pool = $this->createMock(PhpCachePool::class);
        $pool->method('getItem')->willThrowException($exception);

        $this->expectExceptionObject($exception);

        (new CachePoolChain([$pool]))->getItem('key');
    }

    public function testRawBackendExceptionIsRethrownByDefault()
    {
        $exception = new \RuntimeException('backend unavailable');
        $pool = $this->createMock(PhpCachePool::class);
        $pool->method('getItem')->willThrowException($exception);

        $this->expectExceptionObject($exception);

        (new CachePoolChain([$pool]))->getItem('key');
    }

    public function testSkippedPoolFailureIsLoggedAndRemoved()
    {
        $exception = new CachePoolException('failed');
        $failedPool = $this->createMock(PhpCachePool::class);
        $failedPool->expects(self::once())->method('getItem')->willThrowException($exception);

        $fallbackPool = new ArrayCachePool();
        $fallbackPool->save($fallbackPool->getItem('key')->set('value'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with(
                'warning',
                'Removing pool "primary" from chain because it threw an exception when executing "getItem"',
                ['exception' => $exception]
            );

        $chain = new CachePoolChain(
            ['primary' => $failedPool, 'fallback' => $fallbackPool],
            ['skip_on_failure' => true]
        );
        $chain->setLogger($logger);

        self::assertSame('value', $chain->getItem('key')->get());
        self::assertSame('value', $chain->getItem('key')->get());
    }

    public function testSkipOnFailureFallsBackAfterRawBackendException()
    {
        $failedPool = $this->createMock(PhpCachePool::class);
        $failedPool->expects(self::once())
            ->method('getItem')
            ->willThrowException(new \RuntimeException('backend unavailable'));

        $fallbackPool = new ArrayCachePool();
        $fallbackPool->save($fallbackPool->getItem('key')->set('value'));

        $chain = new CachePoolChain([$failedPool, $fallbackPool], ['skip_on_failure' => true]);

        self::assertSame('value', $chain->getItem('key')->get());
        self::assertSame('value', $chain->getItem('key')->get());
    }

    public function testSkipOnFailureAppliesToEveryPoolOperation()
    {
        foreach ([
            'getItems',
            'hasItem',
            'clear',
            'deleteItem',
            'deleteItems',
            'save',
            'saveDeferred',
            'commit',
            'invalidateTags',
        ] as $operation) {
            $failedPool = $this->createMock(PhpCachePool::class);
            $failedPool->method($operation)->willThrowException(new \RuntimeException('backend unavailable'));
            $fallbackPool = new ArrayCachePool();
            $chain = new CachePoolChain([$failedPool, $fallbackPool], ['skip_on_failure' => true]);
            $item = $fallbackPool->getItem('key')->set('value');

            match ($operation) {
                'getItems' => self::assertCount(1, iterator_to_array($chain->getItems(['key']))),
                'hasItem' => self::assertFalse($chain->hasItem('key')),
                'clear' => self::assertTrue($chain->clear()),
                'deleteItem' => self::assertTrue($chain->deleteItem('key')),
                'deleteItems' => self::assertTrue($chain->deleteItems(['key'])),
                'save' => self::assertTrue($chain->save($item)),
                'saveDeferred' => self::assertTrue($chain->saveDeferred($item)),
                'commit' => self::assertTrue($chain->commit()),
                'invalidateTags' => self::assertTrue($chain->invalidateTags(['tag'])),
            };
        }
    }

    public function testGetItemReturnsLastPoolMiss()
    {
        $item = (new CachePoolChain([new ArrayCachePool()]))->getItem('key');

        self::assertSame('key', $item->getKey());
        self::assertFalse($item->isHit());
    }

    public function testGetItemThrowsAfterOnlyPoolFails()
    {
        $pool = $this->createMock(PhpCachePool::class);
        $pool->method('getItem')->willThrowException(new CachePoolException('failed'));
        $chain = new CachePoolChain([$pool], ['skip_on_failure' => true]);

        $this->expectException(\Cache\Adapter\Chain\Exception\NoPoolAvailableException::class);

        $chain->getItem('key');
    }

    #[DataProvider('operationProvider')]
    public function testOperationThrowsAfterEveryPoolFails(string $operation)
    {
        $pool = $this->createMock(PhpCachePool::class);
        $pool->method($operation)->willThrowException(new CachePoolException('failed'));
        $chain = new CachePoolChain([$pool], ['skip_on_failure' => true]);
        $item = new CacheItem('key', true, 'value');

        $this->expectException(\Cache\Adapter\Chain\Exception\NoPoolAvailableException::class);

        match ($operation) {
            'hasItem' => $chain->hasItem('key'),
            'clear' => $chain->clear(),
            'deleteItem' => $chain->deleteItem('key'),
            'deleteItems' => $chain->deleteItems(['key']),
            'save' => $chain->save($item),
            'saveDeferred' => $chain->saveDeferred($item),
            'commit' => $chain->commit(),
            'invalidateTags' => $chain->invalidateTags(['tag']),
        };
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function operationProvider(): iterable
    {
        yield 'has item' => ['hasItem'];
        yield 'clear' => ['clear'];
        yield 'delete item' => ['deleteItem'];
        yield 'delete items' => ['deleteItems'];
        yield 'save' => ['save'];
        yield 'save deferred' => ['saveDeferred'];
        yield 'commit' => ['commit'];
        yield 'invalidate tags' => ['invalidateTags'];
    }

    public function testGetItemsThrowsAfterEveryPoolFails()
    {
        $pool = $this->createMock(PhpCachePool::class);
        $pool->method('getItems')->willThrowException(new CachePoolException('failed'));
        $chain = new CachePoolChain([$pool], ['skip_on_failure' => true]);

        $this->expectException(\Cache\Adapter\Chain\Exception\NoPoolAvailableException::class);

        $chain->getItems(['key']);
    }

    public function testGetItemsTreatsLazyItemFailureAsPoolFailure()
    {
        $item = new CacheItem('key', static function (): array {
            throw new CachePoolException('failed');
        });
        $pool = $this->createMock(PhpCachePool::class);
        $pool->method('getItems')->willReturn(['key' => $item]);
        $chain = new CachePoolChain([$pool], ['skip_on_failure' => true]);

        $this->expectException(\Cache\Adapter\Chain\Exception\NoPoolAvailableException::class);

        $chain->getItems(['key']);
    }

    public function testGetItemsDiscardsPartialResultsFromAFailedPool()
    {
        $failedItem = new CacheItem('second', static function (): array {
            throw new CachePoolException('failed');
        });
        $failedPool = $this->createMock(PhpCachePool::class);
        $failedPool->method('getItems')->willReturn([
            'first' => new CacheItem('first', true, 'bad'),
            'second' => $failedItem,
        ]);

        $fallbackPool = new ArrayCachePool();
        $fallbackPool->save($fallbackPool->getItem('first')->set('good'));
        $fallbackPool->save($fallbackPool->getItem('second')->set('also good'));
        $items = iterator_to_array(
            (new CachePoolChain([$failedPool, $fallbackPool], ['skip_on_failure' => true]))->getItems(['first', 'second'])
        );

        self::assertSame('good', $items['first']->get());
        self::assertSame('also good', $items['second']->get());
    }

    public function testEmptyChainThrows()
    {
        $this->expectException(\Cache\Adapter\Chain\Exception\NoPoolAvailableException::class);

        (new CachePoolChain([]))->getItem('key');
    }

    public function testInvalidateTagDelegatesToPools()
    {
        $pool = $this->createMock(PhpCachePool::class);
        $pool->expects(self::once())->method('invalidateTags')->with(['tag'])->willReturn(true);

        self::assertTrue((new CachePoolChain([$pool]))->invalidateTag('tag'));
    }

    public function testGetItemsSkipsFailureWhileBackfillingPool()
    {
        $miss = new CacheItem('key', false);
        $hit = new CacheItem('key', true, 'value');

        $firstPool = $this->createMock(PhpCachePool::class);
        $firstPool->method('getItems')->with(['key'])->willReturn(['key' => $miss]);
        $firstPool->method('saveDeferred')->with($hit)->willThrowException(new CachePoolException('failed'));

        $secondPool = $this->createMock(PhpCachePool::class);
        $secondPool->method('getItems')->with(['key'])->willReturn(['key' => $hit]);

        $items = iterator_to_array((new CachePoolChain([$firstPool, $secondPool], ['skip_on_failure' => true]))->getItems(['key']));

        self::assertSame('value', $items['key']->get());
    }

    public function testGetItemSkipsFailureWhileBackfillingPool()
    {
        $miss = new CacheItem('key', false);
        $failedPool = $this->createMock(PhpCachePool::class);
        $failedPool->method('getItem')->with('key')->willReturn($miss);
        $failedPool->method('save')->willThrowException(new CachePoolException('failed'));

        $fallbackPool = new ArrayCachePool();
        $fallbackPool->save($fallbackPool->getItem('key')->set('value'));
        $chain = new CachePoolChain([$failedPool, $fallbackPool], ['skip_on_failure' => true]);

        self::assertSame('value', $chain->getItem('key')->get());
        self::assertSame('value', $chain->getItem('key')->get());
    }

    public function testGetItemsKeepsTheHighestPriorityHitForDuplicateKeys()
    {
        $firstPool = new ArrayCachePool();
        $firstPool->save($firstPool->getItem('key')->set('first'));
        $secondPool = new ArrayCachePool();
        $secondPool->save($secondPool->getItem('key')->set('second'));

        $items = iterator_to_array((new CachePoolChain([$firstPool, $secondPool]))->getItems(['key', 'key']));

        self::assertSame('first', $items['key']->get());
    }

    public function testClearCallsEveryPoolAfterFailure()
    {
        $firstPool = $this->createMock(PhpCachePool::class);
        $firstPool->expects(self::once())->method('clear')->willReturn(false);
        $secondPool = $this->createMock(PhpCachePool::class);
        $secondPool->expects(self::once())->method('clear')->willReturn(true);

        self::assertFalse((new CachePoolChain([$firstPool, $secondPool]))->clear());
    }

    public function testDeleteItemCallsEveryPoolAfterFailure()
    {
        $firstPool = $this->createMock(PhpCachePool::class);
        $firstPool->expects(self::once())->method('deleteItem')->with('key')->willReturn(false);
        $secondPool = $this->createMock(PhpCachePool::class);
        $secondPool->expects(self::once())->method('deleteItem')->with('key')->willReturn(true);

        self::assertFalse((new CachePoolChain([$firstPool, $secondPool]))->deleteItem('key'));
    }

    public function testDeleteItemsCallsEveryPoolAfterFailure()
    {
        $firstPool = $this->createMock(PhpCachePool::class);
        $firstPool->expects(self::once())->method('deleteItems')->with(['key'])->willReturn(false);
        $secondPool = $this->createMock(PhpCachePool::class);
        $secondPool->expects(self::once())->method('deleteItems')->with(['key'])->willReturn(true);

        self::assertFalse((new CachePoolChain([$firstPool, $secondPool]))->deleteItems(['key']));
    }

    public function testGetItemsDoesNotTreatInvalidKeyAsPoolFailure()
    {
        $chainPool = new CachePoolChain([new ArrayCachePool(), new ArrayCachePool()], ['skip_on_failure' => true]);

        $this->expectException(\Psr\Cache\InvalidArgumentException::class);

        $chainPool->getItems([true]);
    }

    public function testDeleteItemsDoesNotTreatInvalidKeyAsPoolFailure()
    {
        $chainPool = new CachePoolChain([new ArrayCachePool(), new ArrayCachePool()], ['skip_on_failure' => true]);

        $this->expectException(\Psr\Cache\InvalidArgumentException::class);

        $chainPool->deleteItems([true]);
    }

    public function testGetItemStoreToPrevious()
    {
        $firstPool = new ArrayCachePool();
        $secondPool = new ArrayCachePool();
        $chainPool = new CachePoolChain([$firstPool, $secondPool]);

        $key = 'test_key';
        $item = new CacheItem($key, true, 'value');
        $item->expiresAfter(60);
        $secondPool->save($item);

        $loadedItem = $firstPool->getItem($key);
        $this->assertFalse($loadedItem->isHit());

        $loadedItem = $secondPool->getItem($key);
        $this->assertTrue($loadedItem->isHit());

        $loadedItem = $chainPool->getItem($key);
        $this->assertTrue($loadedItem->isHit());

        $loadedItem = $firstPool->getItem($key);
        $this->assertTrue($loadedItem->isHit());
    }

    public function testGetItemBackfillsStoredTags()
    {
        $firstPool = new ArrayCachePool();
        $secondPool = new ArrayCachePool();
        $source = $secondPool->getItem('key')->set('value')->setTags(['tag']);
        $this->assertTrue($secondPool->save($source));

        $chain = new CachePoolChain([$firstPool, $secondPool]);
        $this->assertSame('value', $chain->getItem('key')->get());
        $this->assertTrue($firstPool->hasItem('key'));

        $this->assertTrue($chain->invalidateTag('tag'));
        $this->assertFalse($firstPool->hasItem('key'));
        $this->assertFalse($secondPool->hasItem('key'));
    }

    public function testGetItemsStoreToPrevious()
    {
        $firstPool = new ArrayCachePool();
        $secondPool = new ArrayCachePool();
        $chainPool = new CachePoolChain([$firstPool, $secondPool]);

        $key = 'test_key';
        $item = new CacheItem($key, true, 'value');
        $item->expiresAfter(60);
        $secondPool->save($item);
        $firstExpirationTime = $item->getExpirationTimestamp();

        $key2 = 'test_key2';
        $item = new CacheItem($key2, true, 'value2');
        $item->expiresAfter(60);
        $secondPool->save($item);
        $secondExpirationTime = $item->getExpirationTimestamp();

        $loadedItem = $firstPool->getItem($key);
        $this->assertFalse($loadedItem->isHit());

        $loadedItem = $firstPool->getItem($key2);
        $this->assertFalse($loadedItem->isHit());

        $loadedItem = $secondPool->getItem($key);
        $this->assertTrue($loadedItem->isHit());

        $loadedItem = $secondPool->getItem($key2);
        $this->assertTrue($loadedItem->isHit());

        $items = iterator_to_array($chainPool->getItems([$key, $key2]));

        $this->assertArrayHasKey($key, $items);
        $this->assertArrayHasKey($key2, $items);

        $this->assertTrue($items[$key]->isHit());
        $this->assertTrue($items[$key2]->isHit());

        $loadedItem = $firstPool->getItem($key);
        $this->assertTrue($loadedItem->isHit());
        $this->assertEquals($firstExpirationTime, $loadedItem->getExpirationTimestamp());

        $loadedItem = $firstPool->getItem($key2);
        $this->assertTrue($loadedItem->isHit());
        $this->assertEquals($secondExpirationTime, $loadedItem->getExpirationTimestamp());
    }

    public function testGetItemsBackfillStoredTags()
    {
        $firstPool = new ArrayCachePool();
        $secondPool = new ArrayCachePool();
        $source = $secondPool->getItem('key')->set('value')->setTags(['tag']);
        $this->assertTrue($secondPool->save($source));

        $chain = new CachePoolChain([$firstPool, $secondPool]);
        $items = iterator_to_array($chain->getItems(['key']));
        $this->assertSame('value', $items['key']->get());
        $this->assertTrue($firstPool->hasItem('key'));

        $this->assertTrue($chain->invalidateTag('tag'));
        $this->assertFalse($firstPool->hasItem('key'));
        $this->assertFalse($secondPool->hasItem('key'));
    }

    public function testGetItemsWithEmptyCache()
    {
        $firstPool = new ArrayCachePool();
        $secondPool = new ArrayCachePool();
        $chainPool = new CachePoolChain([$firstPool, $secondPool]);

        $key = 'test_key';
        $key2 = 'test_key2';

        $items = iterator_to_array($chainPool->getItems([$key, $key2]));

        $this->assertArrayHasKey($key, $items);
        $this->assertArrayHasKey($key2, $items);

        $this->assertFalse($items[$key]->isHit());
        $this->assertFalse($items[$key2]->isHit());
    }
}
