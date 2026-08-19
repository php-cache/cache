<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Taggable\Tests;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\IntegrationTests\TaggableCachePoolTest;
use Cache\Taggable\Exception\InvalidArgumentException;
use Cache\Taggable\ExtensibleTaggablePSR6PoolAdapter;
use Cache\Taggable\TaggablePSR6PoolAdapter;
use Cache\TagInterop\TaggableCacheItemPoolInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter as SymfonyArrayAdapter;

class SeparateTagPoolPSR6AdapterTest extends TaggableCachePoolTest
{
    public function createCachePool(): TaggableCacheItemPoolInterface
    {
        return TaggablePSR6PoolAdapter::makeTaggable(new SymfonyArrayAdapter(), new SymfonyArrayAdapter());
    }

    public function testMakeTaggableReturnsAnExistingTaggablePool()
    {
        $pool = $this->createCachePool();

        self::assertSame($pool, TaggablePSR6PoolAdapter::makeTaggable($pool));
    }

    public function testSubclassCanUseWrappedTagStoreForNativeListOperations()
    {
        $cachePool = new SymfonyArrayAdapter();
        $tagStore = new NativeListTagStore();
        $pool = NativeListTaggablePool::makeTaggable($cachePool, $tagStore);
        $this->assertInstanceOf(NativeListTaggablePool::class, $pool);
        $this->assertSame($cachePool, $pool->wrappedCachePool());
        $this->assertSame($tagStore, $pool->wrappedTagStorePool());
        $this->assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
        $this->assertSame(1, $tagStore->appendCalls);

        $this->assertTrue($pool->invalidateTag('tag'));
        $this->assertFalse($pool->hasItem('key'));
        $this->assertGreaterThan(0, $tagStore->removeItemCalls);
        $this->assertSame(1, $tagStore->removeCalls);
    }

    public function testGetItemsWrapsEveryReturnedItem()
    {
        $pool = $this->createCachePool();
        self::assertTrue($pool->save($pool->getItem('key')->set('value')));

        $items = iterator_to_array($pool->getItems(['key']));

        self::assertSame('key', $items['key']->getKey());
        self::assertSame('value', $items['key']->get());
    }

    public function testGetItemsRejectsAnInvalidItemFromTheWrappedPool()
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItems')->willReturn([new \stdClass()]);
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache);

        $this->expectException(\UnexpectedValueException::class);

        iterator_to_array($pool->getItems(['key']));
    }

    public function testOperationsRejectInvalidKeys()
    {
        $pool = $this->createCachePool();

        foreach ([[''], [true]] as $keys) {
            try {
                iterator_to_array($pool->getItems($keys));
                self::fail('getItems accepted an invalid key');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $pool->hasItem('invalid/key');
    }

    public function testInvalidatingAStaleMissingItemRemovesItsTagIndex()
    {
        $cache = new SymfonyArrayAdapter();
        $tagStore = new SymfonyArrayAdapter();
        $tagStore->save($tagStore->getItem('__tag.tag')->set(['missing']));
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache, $tagStore);

        self::assertTrue($pool->invalidateTag('tag'));
        self::assertFalse($tagStore->hasItem('__tag.tag'));
    }

    public function testStaleTagIndexDoesNotDeleteReplacement()
    {
        $cache = new SymfonyArrayAdapter();
        $tagStore = new SymfonyArrayAdapter();
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache, $tagStore);
        $tagStore->save($tagStore->getItem('__tag.foo')->set(['key']));
        $pool->save($pool->getItem('key')->set('replacement'));

        $pool->invalidateTag('foo');

        self::assertSame('replacement', $pool->getItem('key')->get());
    }

    public function testSaveRejectsAnItemFromAnotherTaggablePool()
    {
        $pool = $this->createCachePool();
        $otherPool = $this->createCachePool();

        $this->expectException(InvalidArgumentException::class);

        $pool->save($otherPool->getItem('key')->set('value'));
    }

    public function testSaveDeferredRejectsAnItemFromAnotherTaggablePool()
    {
        $pool = $this->createCachePool();
        $otherPool = $this->createCachePool();

        $this->expectException(InvalidArgumentException::class);

        $pool->saveDeferred($otherPool->getItem('key')->set('value'));
    }

    public function testFailedDeleteItemKeepsTheTagIndex()
    {
        $cache = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache, new SymfonyArrayAdapter());
        $item = $pool->getItem('key')->set('value')->setTags(['tag']);
        $this->assertTrue($pool->save($item));

        $cache->failDeleteItem = true;
        $this->assertFalse($pool->deleteItem('key'));
        $cache->failDeleteItem = false;

        $this->assertTrue($pool->invalidateTag('tag'));
        $this->assertFalse($pool->hasItem('key'));
    }

    public function testFailedSaveKeepsThePreviousTagIndex()
    {
        $cache = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache, new SymfonyArrayAdapter());
        $this->assertTrue($pool->save($pool->getItem('key')->set('original')->setTags(['old'])));

        $cache->failSave = true;
        $this->assertFalse($pool->save($pool->getItem('key')->set('replacement')->setTags(['new'])));
        $cache->failSave = false;

        $this->assertTrue($pool->invalidateTag('old'));
        $this->assertFalse($pool->hasItem('key'));
    }

    public function testSaveReportsATagStoreWriteFailure()
    {
        $tagStore = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $tagStore->failSave = true;
        $pool = TaggablePSR6PoolAdapter::makeTaggable(new SymfonyArrayAdapter(), $tagStore);

        self::assertFalse($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
    }

    public function testDeleteReportsATagStoreWriteFailure()
    {
        $tagStore = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $pool = TaggablePSR6PoolAdapter::makeTaggable(new SymfonyArrayAdapter(), $tagStore);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));

        $tagStore->failSave = true;

        self::assertFalse($pool->deleteItem('key'));
    }

    public function testSaveReportsATagStoreRemovalFailure()
    {
        $tagStore = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $pool = TaggablePSR6PoolAdapter::makeTaggable(new SymfonyArrayAdapter(), $tagStore);
        self::assertTrue($pool->save($pool->getItem('key')->set('original')->setTags(['tag'])));

        $tagStore->failSave = true;

        self::assertFalse($pool->save($pool->getItem('key')->set('replacement')));
    }

    public function testInvalidationReportsATagStoreDeleteFailure()
    {
        $tagStore = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $pool = TaggablePSR6PoolAdapter::makeTaggable(new SymfonyArrayAdapter(), $tagStore);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));

        $tagStore->failDeleteItem = true;

        self::assertFalse($pool->invalidateTag('tag'));
    }

    public function testInvalidationReportsATagStoreWriteFailure()
    {
        $tagStore = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $pool = TaggablePSR6PoolAdapter::makeTaggable(new SymfonyArrayAdapter(), $tagStore);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));

        $tagStore->failSave = true;

        self::assertFalse($pool->invalidateTag('tag'));
    }

    public function testFailedDeleteItemsKeepsTheTagIndex()
    {
        $cache = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache, new SymfonyArrayAdapter());
        $item = $pool->getItem('key')->set('value')->setTags(['tag']);
        $this->assertTrue($pool->save($item));

        $cache->failDeleteItems = true;
        $this->assertFalse($pool->deleteItems(['key']));
        $cache->failDeleteItems = false;

        $this->assertTrue($pool->invalidateTag('tag'));
        $this->assertFalse($pool->hasItem('key'));
    }

    public function testFailedClearKeepsTheTagIndex()
    {
        $cache = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache, new SymfonyArrayAdapter());
        $item = $pool->getItem('key')->set('value')->setTags(['tag']);
        $this->assertTrue($pool->save($item));

        $cache->failClear = true;
        $this->assertFalse($pool->clear());
        $cache->failClear = false;

        $this->assertTrue($pool->invalidateTag('tag'));
        $this->assertFalse($pool->hasItem('key'));
    }

    public function testFailedClearKeepsTagsForDeferredSave()
    {
        $cache = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache, new SymfonyArrayAdapter());
        $item = $pool->getItem('key')->set('value')->setTags(['tag']);
        $this->assertTrue($pool->saveDeferred($item));

        $cache->failClear = true;
        $this->assertFalse($pool->clear());
        $cache->failClear = false;

        $this->assertTrue($pool->commit());
        $this->assertTrue($pool->invalidateTag('tag'));
        $this->assertFalse($pool->hasItem('key'));
    }

    public function testWrappedPoolCannotCommitAnItemWithoutItsTags()
    {
        $cache = new SymfonyArrayAdapter();
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache, new SymfonyArrayAdapter());
        $item = $pool->getItem('key')->set('value')->setTags(['tag']);
        $this->assertTrue($pool->saveDeferred($item));
        $this->assertTrue($cache->commit());

        $this->assertTrue($pool->invalidateTag('tag'));
        $this->assertFalse($pool->hasItem('key'));
    }

    public function testUnrelatedDeleteCannotCommitAnItemWithoutItsTags()
    {
        $pool = TaggablePSR6PoolAdapter::makeTaggable(new ArrayCachePool(), new SymfonyArrayAdapter());
        $item = $pool->getItem('key')->set('value')->setTags(['tag']);
        $this->assertTrue($pool->saveDeferred($item));
        $this->assertTrue($pool->deleteItem('other'));

        $this->assertTrue($pool->invalidateTag('tag'));
        $this->assertFalse($pool->hasItem('key'));
    }
}

final class NativeListTaggablePool extends ExtensibleTaggablePSR6PoolAdapter
{
    public function wrappedCachePool(): CacheItemPoolInterface
    {
        return $this->getCachePool();
    }

    public function wrappedTagStorePool(): CacheItemPoolInterface
    {
        return $this->getTagStorePool();
    }

    protected function appendListItem(string $name, string $value): bool
    {
        return $this->nativeTagStore()->appendListItem($name, $value);
    }

    protected function removeList(string $name): bool
    {
        return $this->nativeTagStore()->removeList($name);
    }

    protected function removeListItem(string $name, string $key): bool
    {
        return $this->nativeTagStore()->removeListItem($name, $key);
    }

    protected function getList(string $name): array
    {
        return $this->nativeTagStore()->getList($name);
    }

    private function nativeTagStore(): NativeListTagStore
    {
        $tagStore = $this->getTagStorePool();
        if (!$tagStore instanceof NativeListTagStore) {
            throw new \LogicException('A native list tag store is required.');
        }

        return $tagStore;
    }
}

final class NativeListTagStore extends SymfonyArrayAdapter
{
    /** @var array<string, list<string>> */
    private array $lists = [];

    public int $appendCalls = 0;

    public int $removeCalls = 0;

    public int $removeItemCalls = 0;

    public function appendListItem(string $name, string $value): bool
    {
        ++$this->appendCalls;
        $this->lists[$name][] = $value;

        return true;
    }

    public function removeList(string $name): bool
    {
        ++$this->removeCalls;
        unset($this->lists[$name]);

        return true;
    }

    public function removeListItem(string $name, string $key): bool
    {
        ++$this->removeItemCalls;
        $this->lists[$name] = array_values(array_filter(
            $this->lists[$name] ?? [],
            static fn (string $value): bool => $value !== $key,
        ));

        return true;
    }

    /**
     * @return list<string>
     */
    public function getList(string $name): array
    {
        return $this->lists[$name] ?? [];
    }
}
