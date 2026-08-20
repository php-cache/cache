<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Common\Tests;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\PhpCacheItem;
use PHPUnit\Framework\TestCase;

class AbstractCachePoolTest extends TestCase
{
    public function testMissingTagVersionMakesEveryReadPathMiss()
    {
        $store = new GenerationStore();
        $pool = new GenerationPool($store);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));

        unset($store->items['tagv!'.substr(hash('sha256', 'tag'), 0, 59)]);

        self::assertFalse($pool->getItem('key')->isHit());
        self::assertFalse($pool->hasItem('key'));
        self::assertFalse(iterator_to_array($pool->getItems(['key']))['key']->isHit());
    }

    public function testSaveRacingWithInvalidationCannotEscapeTheInvalidation()
    {
        $store = new GenerationStore();
        $first = new GenerationPool($store);
        $second = new GenerationPool($store);
        self::assertTrue($first->save($first->getItem('seed')->set('value')->setTags(['tag'])));

        $store->beforePublicStore = static function () use ($second): void {
            self::assertTrue($second->invalidateTag('tag'));
        };

        self::assertTrue($first->save($first->getItem('during')->set('value')->setTags(['tag'])));
        self::assertFalse($first->hasItem('seed'));
        self::assertFalse($first->hasItem('during'));

        self::assertTrue($second->save($second->getItem('after')->set('value')->setTags(['tag'])));
        self::assertTrue($first->hasItem('after'));
    }

    public function testLostTagIndexMemberCannotEscapeInvalidation()
    {
        $store = new GenerationStore();
        $pool = new GenerationPool($store);
        self::assertTrue($pool->save($pool->getItem('first')->set('value')->setTags(['tag'])));
        self::assertTrue($pool->save($pool->getItem('second')->set('value')->setTags(['tag'])));
        $store->lists['tag!'.substr(hash('sha256', 'tag'), 0, 60)] = ['first'];

        self::assertTrue($pool->invalidateTag('tag'));
        self::assertFalse($pool->hasItem('first'));
        self::assertFalse($pool->hasItem('second'));
    }

    public function testConcurrentFirstSavesCannotCreateAStaleHit()
    {
        $store = new GenerationStore();
        $first = new GenerationPool($store);
        $second = new GenerationPool($store);
        $store->beforeVersionStore = static function () use ($second): void {
            self::assertTrue($second->save($second->getItem('second')->set('value')->setTags(['tag'])));
        };

        self::assertTrue($first->save($first->getItem('first')->set('value')->setTags(['tag'])));
        self::assertNotSame($first->hasItem('first'), $second->hasItem('second'));

        self::assertTrue($first->invalidateTag('tag'));
        self::assertFalse($first->hasItem('first'));
        self::assertFalse($second->hasItem('second'));
    }

    public function testTagVersionWriteFailureStopsTheItemWrite()
    {
        $store = new GenerationStore();
        $store->failVersionWrites = true;
        $pool = new GenerationPool($store);

        self::assertFalse($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
        self::assertArrayNotHasKey('key', $store->items);
    }

    public function testTagVersionDeleteFailureStopsInvalidation()
    {
        $store = new GenerationStore();
        $pool = new GenerationPool($store);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
        $store->failVersionDeletes = true;

        self::assertFalse($pool->invalidateTag('tag'));
        self::assertTrue($pool->hasItem('key'));
    }

    public function testNumericTagCleanupRemovesEachIndexEntryOnce()
    {
        $store = new GenerationStore();
        $store->failMissingListRemovals = true;
        $pool = new GenerationPool($store);
        self::assertTrue($pool->save($pool->getItem('key')->set('first')->setTags(['0'])));

        $item = $pool->getItem('key');
        self::assertSame('first', $item->get());
        $item->set('second')->setTags(['0']);

        self::assertTrue($pool->save($item));
        self::assertSame('second', $pool->getItem('key')->get());
    }

    public function testInvalidationRemovesDeferredTaggedItem()
    {
        $pool = new GenerationPool(new GenerationStore());
        self::assertTrue($pool->saveDeferred($pool->getItem('key')->set('value')->setTags(['tag'])));

        self::assertTrue($pool->invalidateTag('tag'));
        self::assertFalse($pool->hasItem('key'));
    }
}

final class GenerationStore
{
    public ?\Closure $beforePublicStore = null;

    public ?\Closure $beforeVersionStore = null;

    public bool $failVersionDeletes = false;

    public bool $failVersionWrites = false;

    public bool $failMissingListRemovals = false;

    /** @var array<string, array{mixed, list<array{0: string, 1: string}>, int|null}> */
    public array $items = [];

    /** @var array<string, list<string>> */
    public array $lists = [];
}

final class GenerationPool extends AbstractCachePool
{
    public function __construct(private readonly GenerationStore $store)
    {
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        $key = $item->getKey();
        if ($this->store->failVersionWrites && str_starts_with($key, 'tagv!')) {
            return false;
        }
        if (null !== $this->store->beforeVersionStore && str_starts_with($key, 'tagv!')) {
            $callback = $this->store->beforeVersionStore;
            $this->store->beforeVersionStore = null;
            $callback();
        }
        if (null !== $this->store->beforePublicStore && !str_starts_with($key, 'tagv!')) {
            $callback = $this->store->beforePublicStore;
            $this->store->beforePublicStore = null;
            $callback();
        }

        $this->store->items[$key] = [$item->get(), $item->getTagVersions(), $item->getExpirationTimestamp()];

        return true;
    }

    protected function fetchObjectFromCache(string $key): array
    {
        if (!isset($this->store->items[$key])) {
            return [false, null, [], null];
        }

        [$value, $tags, $expiration] = $this->store->items[$key];

        return [true, $value, $tags, $expiration];
    }

    protected function clearAllObjectsFromCache(): bool
    {
        $this->store->items = [];
        $this->store->lists = [];

        return true;
    }

    protected function clearOneObjectFromCache(string $key): bool
    {
        if ($this->store->failVersionDeletes && str_starts_with($key, 'tagv!')) {
            return false;
        }

        unset($this->store->items[$key]);

        return true;
    }

    protected function getList(string $name): array
    {
        return $this->store->lists[$name] ?? [];
    }

    protected function removeList(string $name): bool
    {
        unset($this->store->lists[$name]);

        return true;
    }

    protected function appendListItem(string $name, string $key): bool
    {
        $this->store->lists[$name][] = $key;

        return true;
    }

    protected function removeListItem(string $name, string $key): bool
    {
        $list = $this->store->lists[$name] ?? [];
        if ($this->store->failMissingListRemovals && !\in_array($key, $list, true)) {
            return false;
        }
        $this->store->lists[$name] = array_values(array_diff($list, [$key]));

        return true;
    }
}
