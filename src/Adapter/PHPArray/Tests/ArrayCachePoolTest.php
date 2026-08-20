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
    public function testForeignItemIsRejectedBySave()
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

    public function testForeignItemIsRejectedBySaveDeferred()
    {
        $this->expectException(InvalidArgumentException::class);

        (new ArrayCachePool())->saveDeferred($this->createStub(CacheItemInterface::class));
    }

    public function testStorageExceptionIsWrappedWhenClearing()
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

    public function testStorageExceptionIsWrappedWhenSaving()
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

    public function testStorageExceptionUsesGetItemOperationName()
    {
        $exception = new \RuntimeException('backend down');
        $pool = new ThrowingArrayCachePool();
        $pool->fetchException = $exception;

        try {
            $pool->getItem('key')->isHit();
            self::fail('getItem did not wrap the storage exception');
        } catch (CachePoolException $wrapped) {
            self::assertSame($exception, $wrapped->getPrevious());
            self::assertSame('Exception thrown when executing "getItem". ', $wrapped->getMessage());
        }
    }

    public function testSetMultipleWrapsItemExpirationFailure()
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

    public function testSetMultipleAttemptsEveryItemAndAlwaysCommits()
    {
        $pool = new PartialDeferredArrayCachePool();

        self::assertFalse($pool->setMultiple(['first' => 'one', 'second' => 'two']));
        self::assertSame(['first', 'second'], $pool->attemptedKeys);
        self::assertSame(1, $pool->commitCalls);
        self::assertSame('two', $pool->getItem('second')->get());
    }

    public function testDeletedLimitedItemIsNoLongerTracked()
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

    public function testDirectValueReadsBackingStorage()
    {
        $storage = ['key' => 'value'];
        $pool = new ArrayCachePool(null, $storage);

        self::assertSame('value', $pool->getDirectValue('key'));
        self::assertNull($pool->getDirectValue('missing'));
    }

    public function testMalformedStoredPayloadIsAMiss()
    {
        foreach ([[0 => 'value'], ['value', [123], null]] as $payload) {
            $storage = ['key' => $payload];
            $pool = new ArrayCachePool(null, $storage);

            self::assertFalse($pool->getItem('key')->isHit());
        }
    }

    public function testMalformedTagIndexIsDiscarded()
    {
        $tagKey = 'tag!'.substr(hash('sha256', 'tag'), 0, 60);

        foreach (['invalid', [123]] as $tagIndex) {
            $storage = [$tagKey => $tagIndex];
            $pool = new ArrayCachePool(null, $storage);

            self::assertTrue($pool->invalidateTag('tag'));
            self::assertArrayNotHasKey($tagKey, $storage);
        }
    }

    public function testPublicKeyCannotUseInternalTagIndexNamespace()
    {
        $this->expectException(InvalidArgumentException::class);

        (new ArrayCachePool())->getItem('tag!group');
    }

    public function testPublicKeyCannotUseInternalTagVersionNamespace()
    {
        $this->expectException(InvalidArgumentException::class);

        (new ArrayCachePool())->getItem('tagv!group');
    }

    public function testSavingHierarchicalItemRepairsMalformedPath()
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

    public function testLimitKeepsUpdatedItem()
    {
        $pool = new ArrayCachePool(2);
        $pool->save($pool->getItem('key1')->set('value1'));
        $pool->save($pool->getItem('key2')->set('value2'));

        self::assertTrue($pool->save($pool->getItem('key1')->set('updated')));
        self::assertSame('updated', $pool->getItem('key1')->get());
        self::assertSame('value2', $pool->getItem('key2')->get());
    }

    public function testLimitCanBeReusedAfterClear()
    {
        $pool = new ArrayCachePool(2);
        $pool->save($pool->getItem('key1')->set('value1'));
        $pool->save($pool->getItem('key2')->set('value2'));
        $pool->clear();

        self::assertTrue($pool->save($pool->getItem('key1')->set('replacement')));
        self::assertSame('replacement', $pool->getItem('key1')->get());
    }

    public function testFailedSaveKeepsExistingTagIndex()
    {
        $pool = new FailingArrayCachePool();
        $pool->save($pool->getItem('key')->set('original')->setTags(['foo']));

        $pool->failWrites = true;
        self::assertFalse($pool->save($pool->getItem('key')->set('replacement')->setTags(['bar'])));
        $pool->failWrites = false;

        $pool->invalidateTag('foo');

        self::assertFalse($pool->hasItem('key'));
    }

    public function testDeleteReportsADeferredCommitFailure()
    {
        $pool = new FailingArrayCachePool();
        self::assertTrue($pool->saveDeferred($pool->getItem('deferred')->set('value')->setTags(['tag'])));
        $pool->failWrites = true;

        self::assertFalse($pool->deleteItem('other'));
    }

    public function testDeleteItemsDoesNotPersistRequestedDeferredItems()
    {
        $pool = new FailingArrayCachePool();
        self::assertTrue($pool->saveDeferred($pool->getItem('first')->set('value')));
        self::assertTrue($pool->saveDeferred($pool->getItem('second')->set('value')));
        $pool->failWrites = true;

        self::assertTrue($pool->deleteItems(['first', 'second']));
        self::assertFalse($pool->hasItem('first'));
        self::assertFalse($pool->hasItem('second'));
    }

    public function testCommitDoesNotReplayItemsAfterDeletingAnExpiredDeferredItem()
    {
        $pool = new DuplicateWriteFailingArrayCachePool();
        self::assertTrue($pool->saveDeferred($pool->getItem('expired')->set('old')->expiresAfter(-1)));
        self::assertTrue($pool->saveDeferred($pool->getItem('live')->set('value')));

        self::assertTrue($pool->commit());
        self::assertFalse($pool->hasItem('expired'));
        self::assertSame('value', $pool->getItem('live')->get());
    }

    public function testCommitKeepsFailedAndUnattemptedItemsDeferredAfterAnException()
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

    public function testStaleTagIndexDoesNotDeleteReplacement()
    {
        $pool = new FailingArrayCachePool();
        $pool->addTagEntry('foo', 'key');
        $pool->save($pool->getItem('key')->set('replacement'));

        $pool->invalidateTag('foo');

        self::assertSame('replacement', $pool->getItem('key')->get());
    }

    public function testMissingTagGenerationMakesTaggedItemsMiss()
    {
        $storage = [];
        $pool = new ArrayCachePool(null, $storage);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));

        unset($storage['tagv!'.substr(hash('sha256', 'tag'), 0, 59)]);

        self::assertFalse($pool->hasItem('key'));
        self::assertFalse(iterator_to_array($pool->getItems(['key']))['key']->isHit());
    }

    public function testNumericStringTagsKeepTheirGenerationSnapshots()
    {
        $pool = new ArrayCachePool();

        foreach (['0', '123', '-1'] as $tag) {
            $key = 'key_'.str_replace('-', 'minus_', $tag);
            self::assertTrue($pool->save($pool->getItem($key)->set('value')->setTags([$tag])));
            self::assertTrue($pool->hasItem($key));
            self::assertTrue($pool->invalidateTag($tag));
            self::assertFalse($pool->hasItem($key));
        }
    }

    public function testSaveRacingWithInvalidationCannotEscapeTheInvalidation()
    {
        $storage = [];
        $first = new InterleavingArrayCachePool(null, $storage);
        $second = new ArrayCachePool(null, $storage);
        self::assertTrue($first->save($first->getItem('seed')->set('value')->setTags(['tag'])));

        $first->beforePublicStore = static function () use ($second): void {
            self::assertTrue($second->invalidateTag('tag'));
        };

        self::assertTrue($first->save($first->getItem('during')->set('value')->setTags(['tag'])));
        self::assertFalse($first->hasItem('seed'));
        self::assertFalse($first->hasItem('during'));

        self::assertTrue($second->save($second->getItem('after')->set('value')->setTags(['tag'])));
        self::assertTrue($first->hasItem('after'));
    }

    public function testTagGenerationWriteFailureDoesNotStoreTheItem()
    {
        $storage = [];
        $pool = new FailingTagGenerationArrayCachePool(null, $storage);
        $pool->failGenerationWrites = true;

        self::assertFalse($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
        self::assertArrayNotHasKey('key', $storage);
    }

    public function testTagGenerationDeleteFailureDoesNotDeleteTheItem()
    {
        $storage = [];
        $pool = new FailingTagGenerationArrayCachePool(null, $storage);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
        $pool->failGenerationDeletes = true;

        self::assertFalse($pool->invalidateTag('tag'));
        self::assertTrue($pool->hasItem('key'));
    }

    public function testRemoveListItem()
    {
        $pool = new ArrayCachePool();
        $reflection = new \ReflectionClass($pool::class);
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

final class InterleavingArrayCachePool extends ArrayCachePool
{
    public ?\Closure $beforePublicStore = null;

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        if (null !== $this->beforePublicStore && !str_starts_with($item->getKey(), 'tagv!')) {
            $callback = $this->beforePublicStore;
            $this->beforePublicStore = null;
            $callback();
        }

        return parent::storeItemInCache($item, $ttl);
    }
}

final class FailingTagGenerationArrayCachePool extends ArrayCachePool
{
    public bool $failGenerationDeletes = false;

    public bool $failGenerationWrites = false;

    protected function clearOneObjectFromCache(string $key): bool
    {
        if ($this->failGenerationDeletes && str_starts_with($key, 'tagv!')) {
            return false;
        }

        return parent::clearOneObjectFromCache($key);
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        if ($this->failGenerationWrites && str_starts_with($item->getKey(), 'tagv!')) {
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

    public ?\RuntimeException $fetchException = null;

    public ?\RuntimeException $saveException = null;

    protected function clearAllObjectsFromCache(): bool
    {
        throw $this->clearException ?? new \LogicException('clearException is not configured');
    }

    protected function fetchObjectFromCache(string $key): array
    {
        if (null !== $this->fetchException) {
            throw $this->fetchException;
        }

        return parent::fetchObjectFromCache($key);
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
