<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\PHPArray\Tests;

use Cache\Adapter\Common\Exception\CachePoolException;
use Cache\Adapter\Common\Exception\InvalidArgumentException;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Adapter\PHPArray\ArrayCachePool;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;

class ArrayCachePoolTest extends TestCase
{
    public function testForeignItemIsRejectedBySave(): void
    {
        $pool = new ArrayCachePool();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with(
                'warning',
                'Cache items are not transferable between pools. Item MUST implement PhpCacheItem.',
                self::callback(static fn (array $context): bool => $context['exception'] instanceof InvalidArgumentException)
            );
        $pool->setLogger($logger);

        $this->expectException(InvalidArgumentException::class);

        $pool->save($this->createStub(CacheItemInterface::class));
    }

    public function testForeignItemIsRejectedBySaveDeferred(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ArrayCachePool())->saveDeferred($this->createStub(CacheItemInterface::class));
    }

    public function testStorageExceptionIsWrappedWhenClearing(): void
    {
        $exception = new \RuntimeException('failed');
        $pool = new ThrowingArrayCachePool();
        $pool->clearException = $exception;

        try {
            $pool->clear();
            self::fail('clear did not wrap the storage exception');
        } catch (CachePoolException $wrapped) {
            self::assertSame($exception, $wrapped->getPrevious());
            self::assertSame('Exception thrown when executing "clear". ', $wrapped->getMessage());
        }
    }

    public function testStorageExceptionIsWrappedWhenSaving(): void
    {
        $exception = new \RuntimeException('failed');
        $pool = new ThrowingArrayCachePool();
        $pool->saveException = $exception;

        try {
            $pool->save($pool->getItem('key')->set('value'));
            self::fail('save did not wrap the storage exception');
        } catch (CachePoolException $wrapped) {
            self::assertSame($exception, $wrapped->getPrevious());
            self::assertSame('Exception thrown when executing "save". ', $wrapped->getMessage());
        }
    }

    public function testSetMultipleWrapsItemExpirationFailure(): void
    {
        $exception = new InvalidArgumentException('invalid');
        $item = $this->createMock(PhpCacheItem::class);
        $item->method('set')->willReturnSelf();
        $item->method('expiresAfter')->willThrowException($exception);
        $pool = new FixedItemArrayCachePool($item);

        try {
            $pool->setMultiple(['key' => 'value']);
            self::fail('setMultiple did not wrap the item exception');
        } catch (InvalidArgumentException $wrapped) {
            self::assertSame($exception, $wrapped->getPrevious());
        }
    }

    public function testSetMultipleAttemptsEveryItemAndAlwaysCommits(): void
    {
        $pool = new PartialDeferredArrayCachePool();

        self::assertFalse($pool->setMultiple(['first' => 'one', 'second' => 'two']));
        self::assertSame(['first', 'second'], $pool->attemptedKeys);
        self::assertSame(1, $pool->commitCalls);
        self::assertSame('two', $pool->getItem('second')->get());
    }

    public function testDeletedLimitedItemIsNoLongerTracked(): void
    {
        $pool = new ArrayCachePool(2);
        $pool->save($pool->getItem('key1')->set('value1'));
        $pool->save($pool->getItem('key2')->set('value2'));
        $pool->deleteItem('key1');
        $pool->save($pool->getItem('key3')->set('value3'));
        $pool->save($pool->getItem('key4')->set('value4'));

        self::assertFalse($pool->hasItem('key1'));
        self::assertFalse($pool->hasItem('key2'));
        self::assertTrue($pool->hasItem('key3'));
        self::assertTrue($pool->hasItem('key4'));
    }

    public function testDirectValueReadsBackingStorage(): void
    {
        $storage = ['key' => 'value'];
        $pool = new ArrayCachePool(null, $storage);

        self::assertSame('value', $pool->getDirectValue('key'));
        self::assertNull($pool->getDirectValue('missing'));
    }

    public function testMalformedStoredPayloadIsAMiss(): void
    {
        foreach ([[0 => 'value'], ['value', [123], null]] as $payload) {
            $storage = ['key' => $payload];
            $pool = new ArrayCachePool(null, $storage);

            self::assertFalse($pool->getItem('key')->isHit());
        }
    }

    public function testMalformedTagIndexIsDiscarded(): void
    {
        foreach (['invalid', [123]] as $tagIndex) {
            $storage = ['tag!tag' => $tagIndex];
            $pool = new ArrayCachePool(null, $storage);

            self::assertTrue($pool->invalidateTag('tag'));
            self::assertArrayNotHasKey('tag!tag', $storage);
        }
    }

    public function testSavingHierarchicalItemRepairsMalformedPath(): void
    {
        $storage = ['aaa' => 'invalid'];
        $pool = new ArrayCachePool(null, $storage);

        self::assertTrue($pool->save($pool->getItem('|aaa|bbb')->set('value')));
        self::assertSame('value', $pool->getItem('|aaa|bbb')->get());
    }

    public function testLimit()
    {
        $pool = new ArrayCachePool(2);
        $item = $pool->getItem('key1')->set('value1');
        $pool->save($item);

        $item = $pool->getItem('key2')->set('value2');
        $pool->save($item);

        // Both items should be in the pool, nothing strange yet
        $this->assertTrue($pool->hasItem('key1'));
        $this->assertTrue($pool->hasItem('key2'));

        $item = $pool->getItem('key3')->set('value3');
        $pool->save($item);

        // First item should be dropped
        $this->assertFalse($pool->hasItem('key1'));
        $this->assertTrue($pool->hasItem('key2'));
        $this->assertTrue($pool->hasItem('key3'));

        $this->assertFalse($pool->getItem('key1')->isHit());
        $this->assertTrue($pool->getItem('key2')->isHit());
        $this->assertTrue($pool->getItem('key3')->isHit());

        $item = $pool->getItem('key4')->set('value4');
        $pool->save($item);

        // Only the last two items should be in place
        $this->assertFalse($pool->hasItem('key1'));
        $this->assertFalse($pool->hasItem('key2'));
        $this->assertTrue($pool->hasItem('key3'));
        $this->assertTrue($pool->hasItem('key4'));
    }

    public function testLimitKeepsUpdatedItem(): void
    {
        $pool = new ArrayCachePool(2);
        $pool->save($pool->getItem('key1')->set('value1'));
        $pool->save($pool->getItem('key2')->set('value2'));

        self::assertTrue($pool->save($pool->getItem('key1')->set('updated')));
        self::assertSame('updated', $pool->getItem('key1')->get());
        self::assertSame('value2', $pool->getItem('key2')->get());
    }

    public function testLimitCanBeReusedAfterClear(): void
    {
        $pool = new ArrayCachePool(2);
        $pool->save($pool->getItem('key1')->set('value1'));
        $pool->save($pool->getItem('key2')->set('value2'));
        $pool->clear();

        self::assertTrue($pool->save($pool->getItem('key1')->set('replacement')));
        self::assertSame('replacement', $pool->getItem('key1')->get());
    }

    public function testFailedSaveKeepsExistingTagIndex(): void
    {
        $pool = new FailingArrayCachePool();
        $pool->save($pool->getItem('key')->set('original')->setTags(['foo']));

        $pool->failWrites = true;
        self::assertFalse($pool->save($pool->getItem('key')->set('replacement')->setTags(['bar'])));
        $pool->failWrites = false;

        $pool->invalidateTag('foo');

        self::assertFalse($pool->hasItem('key'));
    }

    public function testDeleteReportsADeferredCommitFailure(): void
    {
        $pool = new FailingArrayCachePool();
        self::assertTrue($pool->saveDeferred($pool->getItem('deferred')->set('value')->setTags(['tag'])));
        $pool->failWrites = true;

        self::assertFalse($pool->deleteItem('other'));
    }

    public function testDeleteItemsDoesNotPersistRequestedDeferredItems(): void
    {
        $pool = new FailingArrayCachePool();
        self::assertTrue($pool->saveDeferred($pool->getItem('first')->set('value')));
        self::assertTrue($pool->saveDeferred($pool->getItem('second')->set('value')));
        $pool->failWrites = true;

        self::assertTrue($pool->deleteItems(['first', 'second']));
        self::assertFalse($pool->hasItem('first'));
        self::assertFalse($pool->hasItem('second'));
    }

    public function testCommitDoesNotReplayItemsAfterDeletingAnExpiredDeferredItem(): void
    {
        $pool = new DuplicateWriteFailingArrayCachePool();
        self::assertTrue($pool->saveDeferred($pool->getItem('expired')->set('old')->expiresAfter(-1)));
        self::assertTrue($pool->saveDeferred($pool->getItem('live')->set('value')));

        self::assertTrue($pool->commit());
        self::assertFalse($pool->hasItem('expired'));
        self::assertSame('value', $pool->getItem('live')->get());
    }

    public function testCommitKeepsFailedAndUnattemptedItemsDeferredAfterAnException(): void
    {
        $pool = new OneTimeThrowingArrayCachePool();
        self::assertTrue($pool->saveDeferred($pool->getItem('first')->set('one')));
        self::assertTrue($pool->saveDeferred($pool->getItem('blocked')->set('two')));
        self::assertTrue($pool->saveDeferred($pool->getItem('last')->set('three')));

        try {
            $pool->commit();
            self::fail('commit did not wrap the storage exception');
        } catch (CachePoolException) {
        }

        self::assertTrue($pool->commit());
        self::assertSame(['first', 'blocked', 'blocked', 'last'], $pool->attemptedKeys);
        self::assertSame('one', $pool->getItem('first')->get());
        self::assertSame('two', $pool->getItem('blocked')->get());
        self::assertSame('three', $pool->getItem('last')->get());
    }

    public function testStaleTagIndexDoesNotDeleteReplacement(): void
    {
        $pool = new FailingArrayCachePool();
        $pool->addTagEntry('foo', 'key');
        $pool->save($pool->getItem('key')->set('replacement'));

        $pool->invalidateTag('foo');

        self::assertSame('replacement', $pool->getItem('key')->get());
    }

    public function testRemoveListItem()
    {
        $pool = new ArrayCachePool();
        $reflection = new \ReflectionClass(get_class($pool));
        $method = $reflection->getMethod('removeListItem');

        // Add a tagged item to test list removal
        $item = $pool->getItem('key1')->set('value1')->setTags(['tag1']);
        $pool->save($item);

        $this->assertTrue($pool->hasItem('key1'));
        $this->assertTrue($pool->deleteItem('key1'));
        $this->assertFalse($pool->hasItem('key1'));

        // Trying to remove an item in an un-existing tag list should not throw
        // Notice error / Exception in strict mode
        $this->assertTrue($method->invokeArgs($pool, ['tag1', 'key1']));
    }
}

final class FailingArrayCachePool extends ArrayCachePool
{
    public bool $failWrites = false;

    public function addTagEntry(string $tag, string $key): void
    {
        $this->appendListItem($this->getTagKey($tag), $key);
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        if ($this->failWrites) {
            return false;
        }

        return parent::storeItemInCache($item, $ttl);
    }
}

final class DuplicateWriteFailingArrayCachePool extends ArrayCachePool
{
    private int $liveWrites = 0;

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        if ('live' === $item->getKey() && 1 < ++$this->liveWrites) {
            return false;
        }

        return parent::storeItemInCache($item, $ttl);
    }
}

final class OneTimeThrowingArrayCachePool extends ArrayCachePool
{
    /** @var list<string> */
    public array $attemptedKeys = [];

    private bool $throw = true;

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        $this->attemptedKeys[] = $item->getKey();
        if ('blocked' === $item->getKey() && $this->throw) {
            $this->throw = false;

            throw new \RuntimeException('failed');
        }

        return parent::storeItemInCache($item, $ttl);
    }
}

final class ThrowingArrayCachePool extends ArrayCachePool
{
    public ?\RuntimeException $clearException = null;

    public ?\RuntimeException $saveException = null;

    protected function clearAllObjectsFromCache(): bool
    {
        throw $this->clearException ?? new \LogicException('clearException is not configured');
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        throw $this->saveException ?? new \LogicException('saveException is not configured');
    }
}

final class FixedItemArrayCachePool extends ArrayCachePool
{
    public function __construct(private readonly PhpCacheItem $item)
    {
    }

    public function getItem(string $key): PhpCacheItem
    {
        return $this->item;
    }
}

final class PartialDeferredArrayCachePool extends ArrayCachePool
{
    /** @var list<string> */
    public array $attemptedKeys = [];

    public int $commitCalls = 0;

    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->attemptedKeys[] = $item->getKey();

        return 'first' !== $item->getKey() && parent::saveDeferred($item);
    }

    public function commit(): bool
    {
        ++$this->commitCalls;

        return parent::commit();
    }
}
