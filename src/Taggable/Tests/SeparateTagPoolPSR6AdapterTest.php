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
use Cache\Taggable\TaggablePSR6PoolAdapter;
use Cache\TagInterop\TaggableCacheItemPoolInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter as SymfonyArrayAdapter;
use Symfony\Component\Clock\MockClock;

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

    public function testSubclassCanUseWrappedTagStoreForGenerationOperations()
    {
        $cachePool = new SymfonyArrayAdapter();
        $tagStore = new NativeGenerationTagStore();
        $pool = NativeGenerationTaggablePool::makeTaggable($cachePool, $tagStore);
        $this->assertInstanceOf(NativeGenerationTaggablePool::class, $pool);
        $this->assertSame($cachePool, $pool->wrappedCachePool());
        $this->assertSame($tagStore, $pool->wrappedTagStorePool());
        $this->assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
        $this->assertSame(1, $tagStore->writeCalls);
        $this->assertSame(2, $tagStore->readCalls);

        $this->assertTrue($pool->invalidateTag('tag'));
        $this->assertFalse($pool->hasItem('key'));
        $this->assertSame(1, $tagStore->deleteCalls);
    }

    public function testGetItemsWrapsEveryReturnedItem()
    {
        $pool = $this->createCachePool();
        self::assertTrue($pool->save($pool->getItem('key')->set('value')));

        $items = iterator_to_array($pool->getItems(['key']));

        self::assertSame('key', $items['key']->getKey());
        self::assertSame('value', $items['key']->get());
    }

    public function testSavingAnUnmodifiedFetchedItemPreservesItsValueAndTags()
    {
        $pool = $this->createCachePool();
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));

        $item = $pool->getItem('key');
        self::assertTrue($item->isHit());
        self::assertTrue($pool->save($item));

        self::assertSame('value', $pool->getItem('key')->get());
        self::assertTrue($pool->invalidateTag('tag'));
        self::assertFalse($pool->hasItem('key'));
    }

    public function testSavePausedAfterGenerationReadCannotSurviveConcurrentInvalidation()
    {
        $cache = new SymfonyArrayAdapter();
        $tagStore = new SymfonyArrayAdapter();
        $writer = InterleavingGenerationTaggablePool::makeTaggable($cache, $tagStore);
        $invalidator = TaggablePSR6PoolAdapter::makeTaggable($cache, $tagStore);
        self::assertTrue($invalidator->save($invalidator->getItem('old')->set('old')->setTags(['tag'])));

        $racingItem = $writer->getItem('racing')->set('racing')->setTags(['tag']);
        $writer->afterGenerationRead = static function () use ($invalidator) {
            self::assertTrue($invalidator->invalidateTag('tag'));
        };

        self::assertTrue($writer->save($racingItem));
        self::assertFalse($writer->hasItem('old'));
        self::assertFalse($writer->hasItem('racing'));
    }

    public function testConcurrentFirstSavesCannotCreateAStaleHit()
    {
        $cache = new SymfonyArrayAdapter();
        $tagStore = new SymfonyArrayAdapter();
        $first = InterleavingGenerationTaggablePool::makeTaggable($cache, $tagStore);
        $second = TaggablePSR6PoolAdapter::makeTaggable($cache, $tagStore);
        $firstItem = $first->getItem('first')->set('first')->setTags(['tag']);
        $first->afterGenerationRead = static function () use ($second) {
            self::assertTrue($second->save($second->getItem('second')->set('second')->setTags(['tag'])));
        };

        self::assertTrue($first->save($firstItem));
        self::assertTrue($first->hasItem('first'));
        self::assertFalse($first->hasItem('second'));
        self::assertTrue($first->invalidateTag('tag'));
        self::assertFalse($first->hasItem('first'));
        self::assertFalse($first->hasItem('second'));
    }

    public function testConcurrentInvalidationsBothSucceed()
    {
        $cache = new SymfonyArrayAdapter();
        $tagStore = new SymfonyArrayAdapter();
        $first = InterleavingGenerationTaggablePool::makeTaggable($cache, $tagStore);
        $second = TaggablePSR6PoolAdapter::makeTaggable($cache, $tagStore);
        self::assertTrue($first->save($first->getItem('key')->set('value')->setTags(['tag'])));
        $first->afterGenerationDelete = static function () use ($second) {
            self::assertTrue($second->invalidateTag('tag'));
        };

        self::assertTrue($first->invalidateTag('tag'));
        self::assertFalse($first->hasItem('key'));
    }

    public function testHasItemAndGetItemsRejectInvalidatedGenerations()
    {
        $pool = $this->createCachePool();
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
        self::assertTrue($pool->invalidateTag('tag'));

        self::assertFalse($pool->hasItem('key'));
        $items = iterator_to_array($pool->getItems(['key']));
        self::assertFalse($items['key']->isHit());
        self::assertNull($items['key']->get());
    }

    public function testItemHitAndValueRemainStableAfterLookup()
    {
        $pool = $this->createCachePool();
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
        $item = $pool->getItem('key');
        self::assertTrue($item->isHit());

        self::assertTrue($pool->invalidateTag('tag'));

        self::assertSame('value', $item->get());
        self::assertTrue($item->isHit());
        self::assertFalse($pool->getItem('key')->isHit());
    }

    public function testMissingGenerationFailsClosed()
    {
        $cache = new SymfonyArrayAdapter();
        $tagStore = new SymfonyArrayAdapter();
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache, $tagStore);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
        self::assertTrue($tagStore->deleteItem($this->tagMetadataKey('tag')));

        self::assertFalse($pool->hasItem('key'));
        self::assertNull($pool->getItem('key')->get());
    }

    public function testMalformedGenerationFailsReadsAndSavesClosed()
    {
        $cache = new SymfonyArrayAdapter();
        $tagStore = new SymfonyArrayAdapter();
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache, $tagStore);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
        self::assertTrue($tagStore->save($tagStore->getItem($this->tagMetadataKey('tag'))->set(['invalid'])));

        self::assertFalse($pool->hasItem('key'));
        self::assertFalse($pool->save($pool->getItem('replacement')->set('value')->setTags(['tag'])));
        self::assertFalse($cache->hasItem('replacement'));
    }

    public function testGenerationOverridesTheDefaultLifetimeWithASigned32BitBound()
    {
        $clock = new MockClock('2038-01-19T03:13:00+00:00');
        $tagStore = new SymfonyArrayAdapter(60, clock: $clock);
        $pool = TaggablePSR6PoolAdapter::makeTaggable(new SymfonyArrayAdapter(), $tagStore);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));

        $clock->sleep(61);

        self::assertTrue($tagStore->hasItem($this->tagMetadataKey('tag')));

        $clock->sleep(7);

        self::assertFalse($tagStore->hasItem($this->tagMetadataKey('tag')));
    }

    public function testLegacyTaggedPayloadFailsClosed()
    {
        $cache = new SymfonyArrayAdapter();
        self::assertTrue($cache->save($cache->getItem('key')->set([
            'value' => 'legacy',
            'tags' => ['tag' => 'tag'],
        ])));
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache, new SymfonyArrayAdapter());

        self::assertFalse($pool->hasItem('key'));
        self::assertNull($pool->getItem('key')->get());
    }

    public function testNumericStringTagsRemainValid()
    {
        $pool = $this->createCachePool();

        foreach (['0', '123', '-1'] as $tag) {
            $key = 'key_'.str_replace('-', 'minus_', $tag);
            self::assertTrue($pool->save($pool->getItem($key)->set('value')->setTags([$tag])));
            self::assertTrue($pool->hasItem($key));
            self::assertSame('value', $pool->getItem($key)->get());
            $previousTags = $pool->getItem($key)->getPreviousTags();
            self::assertContainsOnly('string', array_keys($previousTags));
            self::assertContains($tag, $previousTags);
            self::assertTrue($pool->invalidateTag($tag));
            self::assertFalse($pool->hasItem($key));
        }
    }

    public function testInvalidationValidatesEveryTagBeforeDeletingMetadata()
    {
        $pool = $this->createCachePool();
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['valid'])));

        foreach ([['valid', ''], ['valid', 'bad/tag'], ['valid', 2]] as $tags) {
            try {
                $pool->invalidateTags($tags);
                self::fail('invalidateTags accepted an invalid tag');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }

            self::assertTrue($pool->hasItem('key'));
        }

        $this->expectException(InvalidArgumentException::class);

        $pool->invalidateTag('');
    }

    public function testChangingTagsOnAnInvalidatedItemCannotRestoreItsValue()
    {
        foreach ([['tag'], ['replacement'], []] as $replacementTags) {
            $pool = $this->createCachePool();
            self::assertTrue($pool->save($pool->getItem('key')->set('stale')->setTags(['tag'])));
            self::assertTrue($pool->invalidateTag('tag'));
            $item = $pool->getItem('key');
            self::assertFalse($item->isHit());
            self::assertNull($item->get());

            self::assertTrue($pool->save($item->setTags($replacementTags)));

            $saved = $pool->getItem('key');
            self::assertTrue($saved->isHit());
            self::assertNull($saved->get());
        }
    }

    public function testMetadataKeysUseThePortablePsr6AlphabetAndLength()
    {
        $pool = MetadataKeyTaggablePool::makeTaggable(new SymfonyArrayAdapter(), new SymfonyArrayAdapter());
        self::assertInstanceOf(MetadataKeyTaggablePool::class, $pool);

        foreach (['tag with spaces!', str_repeat('x', 64)] as $tag) {
            $metadataKey = $pool->metadataKey($tag);
            self::assertLessThanOrEqual(64, \strlen($metadataKey));
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_.]+$/D', $metadataKey);
        }
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

    public function testSharedPoolRejectsItsTagMetadataNamespace()
    {
        $this->expectException(InvalidArgumentException::class);

        $backend = new SymfonyArrayAdapter();
        TaggablePSR6PoolAdapter::makeTaggable($backend)->getItem('__tag.group');
    }

    public function testSeparatePoolAcceptsTheDefaultTagMetadataNamespace()
    {
        $pool = $this->createCachePool();

        self::assertTrue($pool->save($pool->getItem('__tag.group')->set('value')));
        self::assertSame('value', $pool->getItem('__tag.group')->get());
    }

    public function testSubclassPrefixReservesItsTagMetadataNamespace()
    {
        $backend = new SymfonyArrayAdapter();
        $pool = CustomPrefixTaggablePool::makeTaggable($backend);
        self::assertTrue($pool->save($pool->getItem('__tag.group')->set('value')));

        $this->expectException(InvalidArgumentException::class);

        $pool->getItem('__custom_tag.group');
    }

    public function testInvalidatingAStaleGenerationRemovesIt()
    {
        $cache = new SymfonyArrayAdapter();
        $tagStore = new SymfonyArrayAdapter();
        $tagKey = $this->tagMetadataKey('tag');
        $tagStore->save($tagStore->getItem($tagKey)->set(['missing']));
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache, $tagStore);

        self::assertTrue($pool->invalidateTag('tag'));
        self::assertFalse($tagStore->hasItem($tagKey));
    }

    public function testStaleTagMetadataDoesNotInvalidateUntaggedReplacement()
    {
        $cache = new SymfonyArrayAdapter();
        $tagStore = new SymfonyArrayAdapter();
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache, $tagStore);
        $tagStore->save($tagStore->getItem($this->tagMetadataKey('foo'))->set(['key']));
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

    public function testFailedDeleteItemKeepsItsGenerationValid()
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

    public function testFailedSaveKeepsThePreviousGenerationValid()
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
        $cache = new SymfonyArrayAdapter();
        $tagStore = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $tagStore->failSave = true;
        $pool = TaggablePSR6PoolAdapter::makeTaggable($cache, $tagStore);

        self::assertFalse($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
        self::assertFalse($cache->hasItem('key'));
    }

    public function testDeleteDoesNotWriteTagMetadata()
    {
        $tagStore = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $pool = TaggablePSR6PoolAdapter::makeTaggable(new SymfonyArrayAdapter(), $tagStore);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));

        $tagStore->failSave = true;

        self::assertTrue($pool->deleteItem('key'));
        self::assertFalse($pool->hasItem('key'));
    }

    public function testSavingWithoutTagsDoesNotWriteTagMetadata()
    {
        $tagStore = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $pool = TaggablePSR6PoolAdapter::makeTaggable(new SymfonyArrayAdapter(), $tagStore);
        self::assertTrue($pool->save($pool->getItem('key')->set('original')->setTags(['tag'])));

        $tagStore->failSave = true;

        self::assertTrue($pool->save($pool->getItem('key')->set('replacement')));
        self::assertTrue($pool->invalidateTag('tag'));
        self::assertTrue($pool->hasItem('key'));
    }

    public function testInvalidationReportsATagStoreDeleteFailure()
    {
        $tagStore = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $pool = TaggablePSR6PoolAdapter::makeTaggable(new SymfonyArrayAdapter(), $tagStore);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));

        $tagStore->failDeleteItem = true;

        self::assertFalse($pool->invalidateTag('tag'));
    }

    public function testInvalidationDoesNotWriteTagMetadata()
    {
        $tagStore = new FailingDeleteCachePool(new SymfonyArrayAdapter());
        $pool = TaggablePSR6PoolAdapter::makeTaggable(new SymfonyArrayAdapter(), $tagStore);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));

        $tagStore->failSave = true;

        self::assertTrue($pool->invalidateTag('tag'));
        self::assertFalse($pool->hasItem('key'));
    }

    public function testFailedDeleteItemsKeepTheirGenerationsValid()
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

    public function testFailedClearKeepsGenerationsValid()
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

    private function tagMetadataKey(string $tag): string
    {
        return '__tag.'.substr(hash('sha256', $tag), 0, 58);
    }
}

final class NativeGenerationTaggablePool extends TaggablePSR6PoolAdapter
{
    public function wrappedCachePool(): CacheItemPoolInterface
    {
        return $this->cachePool;
    }

    public function wrappedTagStorePool(): CacheItemPoolInterface
    {
        return $this->tagStorePool;
    }

    protected function readTagGeneration(string $name): string|false|null
    {
        return $this->nativeTagStore()->readTagGeneration($name);
    }

    protected function writeTagGeneration(string $name, string $generation): bool
    {
        return $this->nativeTagStore()->writeTagGeneration($name, $generation);
    }

    protected function deleteTagGeneration(string $name): bool
    {
        return $this->nativeTagStore()->deleteTagGeneration($name);
    }

    private function nativeTagStore(): NativeGenerationTagStore
    {
        $tagStore = $this->tagStorePool;
        if (!$tagStore instanceof NativeGenerationTagStore) {
            throw new \LogicException('A native generation tag store is required.');
        }

        return $tagStore;
    }
}

final class MetadataKeyTaggablePool extends TaggablePSR6PoolAdapter
{
    public function metadataKey(string $tag): string
    {
        return $this->getTagKey($tag);
    }
}

final class CustomPrefixTaggablePool extends TaggablePSR6PoolAdapter
{
    protected function getTagKeyPrefix(): string
    {
        return '__custom_tag.';
    }
}

final class NativeGenerationTagStore extends SymfonyArrayAdapter
{
    public int $deleteCalls = 0;

    /** @var array<string, string> */
    private array $generations = [];

    public int $readCalls = 0;

    public int $writeCalls = 0;

    public function readTagGeneration(string $name): ?string
    {
        ++$this->readCalls;

        return $this->generations[$name] ?? null;
    }

    public function writeTagGeneration(string $name, string $generation): bool
    {
        ++$this->writeCalls;
        $this->generations[$name] = $generation;

        return true;
    }

    public function deleteTagGeneration(string $name): bool
    {
        ++$this->deleteCalls;
        unset($this->generations[$name]);

        return true;
    }
}

final class InterleavingGenerationTaggablePool extends TaggablePSR6PoolAdapter
{
    public ?\Closure $afterGenerationDelete = null;

    public ?\Closure $afterGenerationRead = null;

    protected function deleteTagGeneration(string $name): bool
    {
        $deleted = parent::deleteTagGeneration($name);
        if (null !== $this->afterGenerationDelete) {
            $callback = $this->afterGenerationDelete;
            $this->afterGenerationDelete = null;
            $callback();
        }

        return $deleted;
    }

    protected function readTagGeneration(string $name): string|false|null
    {
        $generation = parent::readTagGeneration($name);
        if (null !== $this->afterGenerationRead) {
            $callback = $this->afterGenerationRead;
            $this->afterGenerationRead = null;
            $callback();
        }

        return $generation;
    }
}
