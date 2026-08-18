<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Namespaced\Tests;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Namespaced\NamespacedCachePool;
use Cache\TagInterop\TaggableCacheItemInterface;
use Cache\TagInterop\TaggableCacheItemPoolInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;

/**
 * We should not use constants on interfaces in the tests. Tests should break if the constant is changed.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class NamespacedCachePoolTest extends TestCase
{
    public function testCreatePreservesNativeTagSupport(): void
    {
        $pool = NamespacedCachePool::create(new ArrayCachePool(), 'namespace');

        $this->assertInstanceOf(TaggableCacheItemPoolInterface::class, $pool);
        $item = $pool->getItem('key');
        $this->assertInstanceOf(TaggableCacheItemInterface::class, $item);
        $this->assertTrue($pool->save($item->set('value')->setTags(['tag'])));
        $storedItem = $pool->getItem('key');
        $this->assertInstanceOf(TaggableCacheItemInterface::class, $storedItem);
        $this->assertContainsOnlyInstancesOf(TaggableCacheItemInterface::class, iterator_to_array($pool->getItems(['key'])));
        $this->assertSame(['tag' => 'tag'], $storedItem->getPreviousTags());
        $this->assertTrue($pool->invalidateTags(['tag']));
        $this->assertFalse($pool->hasItem('key'));

        $this->assertTrue($pool->save($pool->getItem('second')->set('value')->setTags(['tag'])));
        $this->assertTrue($pool->invalidateTag('tag'));
        $this->assertFalse($pool->hasItem('second'));
    }

    public function testTagInvalidationIsIsolatedBetweenNamespaces(): void
    {
        $backend = new ArrayCachePool();
        $first = NamespacedCachePool::create($backend, 'first');
        $second = NamespacedCachePool::create($backend, 'second');

        $this->assertInstanceOf(TaggableCacheItemPoolInterface::class, $first);
        $this->assertInstanceOf(TaggableCacheItemPoolInterface::class, $second);
        $this->assertTrue($first->save($first->getItem('key')->set('first')->setTags(['shared'])));
        $this->assertTrue($second->save($second->getItem('key')->set('second')->setTags(['shared'])));
        $this->assertSame(['shared' => 'shared'], $first->getItem('key')->getPreviousTags());
        $this->assertSame(['shared' => 'shared'], $second->getItem('key')->getPreviousTags());

        $this->assertTrue($first->invalidateTag('shared'));

        $this->assertFalse($first->hasItem('key'));
        $this->assertTrue($second->hasItem('key'));
        $this->assertSame('second', $second->getItem('key')->get());
    }

    public function testTagInvalidationIsIsolatedInNestedNamespaces(): void
    {
        $backend = new ArrayCachePool();
        $outer = NamespacedCachePool::create($backend, 'outer');
        $inner = NamespacedCachePool::create($outer, 'inner');

        $this->assertInstanceOf(TaggableCacheItemPoolInterface::class, $outer);
        $this->assertInstanceOf(TaggableCacheItemPoolInterface::class, $inner);
        $this->assertTrue($outer->save($outer->getItem('key')->set('outer')->setTags(['shared'])));
        $this->assertTrue($inner->save($inner->getItem('key')->set('inner')->setTags(['shared'])));

        $this->assertTrue($inner->invalidateTag('shared'));

        $this->assertFalse($inner->hasItem('key'));
        $this->assertTrue($outer->hasItem('key'));
        $this->assertSame('outer', $outer->getItem('key')->get());
    }

    public function testCreateDoesNotAdvertiseTagsForAGenericPool(): void
    {
        $pool = NamespacedCachePool::create($this->getHierarchyCacheStub(), 'namespace');

        $this->assertNotInstanceOf(TaggableCacheItemPoolInterface::class, $pool);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidIterableKeyProvider(): iterable
    {
        yield 'coercible scalar' => [true];
        yield 'object' => [new \stdClass()];
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getHierarchyCacheStub()
    {
        return $this->getMockBuilder(HelperInterface::class)->onlyMethods(
            ['getItem', 'getItems', 'hasItem', 'clear', 'deleteItem', 'deleteItems', 'save', 'saveDeferred', 'commit']
        )->getMock();
    }

    public function testGetItem()
    {
        $namespace = 'ns';
        $key = 'key';
        $returnValue = $this->createMock(CacheItemInterface::class);
        $returnValue->method('getKey')->willReturn('|'.$namespace.'|'.$key);

        $stub = $this->getHierarchyCacheStub();
        $stub->expects($this->once())->method('getItem')->with('|'.$namespace.'|'.$key)->willReturn($returnValue);

        $pool = new NamespacedCachePool($stub, $namespace);
        $this->assertSame($key, $pool->getItem($key)->getKey());
    }

    public function testGetItems()
    {
        $namespace = 'ns';
        $key0 = 'key0';
        $key1 = 'key1';
        $item0 = $this->createMock(CacheItemInterface::class);
        $item1 = $this->createMock(CacheItemInterface::class);
        $item0->method('getKey')->willReturn('|'.$namespace.'|'.$key0);
        $item1->method('getKey')->willReturn('|'.$namespace.'|'.$key1);
        $returnValue = ['|'.$namespace.'|'.$key0 => $item0, '|'.$namespace.'|'.$key1 => $item1];

        $stub = $this->getHierarchyCacheStub();
        $stub->expects($this->once())->method('getItems')->with(['|'.$namespace.'|'.$key0, '|'.$namespace.'|'.$key1])->willReturn($returnValue);

        $pool = new NamespacedCachePool($stub, $namespace);
        $items = iterator_to_array($pool->getItems([$key0, $key1]));

        $this->assertSame([$key0, $key1], array_keys($items));
        $this->assertSame($key0, $items[$key0]->getKey());
        $this->assertSame($key1, $items[$key1]->getKey());
    }

    #[DataProvider('invalidIterableKeyProvider')]
    public function testGetItemsRejectsNonStringKeys(mixed $key): void
    {
        $pool = new NamespacedCachePool($this->getHierarchyCacheStub(), 'ns');

        $this->expectException(\Psr\Cache\InvalidArgumentException::class);

        $pool->getItems([$key]);
    }

    public function testHasItem()
    {
        $namespace = 'ns';
        $key = 'key';
        $returnValue = true;

        $stub = $this->getHierarchyCacheStub();
        $stub->expects($this->once())->method('hasItem')->with('|'.$namespace.'|'.$key)->willReturn($returnValue);

        $pool = new NamespacedCachePool($stub, $namespace);
        $this->assertEquals($returnValue, $pool->hasItem($key));
    }

    public function testClear()
    {
        $namespace = 'ns';
        $key = 'key';
        $returnValue = true;

        $stub = $this->getHierarchyCacheStub();
        $stub->expects($this->once())->method('deleteItem')->with('|'.$namespace)->willReturn($returnValue);

        $pool = new NamespacedCachePool($stub, $namespace);
        $this->assertEquals($returnValue, $pool->clear($key));
    }

    public function testDeleteItem()
    {
        $namespace = 'ns';
        $key = 'key';
        $returnValue = true;

        $stub = $this->getHierarchyCacheStub();
        $stub->expects($this->once())->method('deleteItem')->with('|'.$namespace.'|'.$key)->willReturn($returnValue);

        $pool = new NamespacedCachePool($stub, $namespace);
        $this->assertEquals($returnValue, $pool->deleteItem($key));
    }

    public function testDeleteItems()
    {
        $namespace = 'ns';
        $key0 = 'key0';
        $key1 = 'key1';
        $returnValue = true;

        $stub = $this->getHierarchyCacheStub();
        $stub->expects($this->once())->method('deleteItems')->with(['|'.$namespace.'|'.$key0, '|'.$namespace.'|'.$key1])->willReturn($returnValue);

        $pool = new NamespacedCachePool($stub, $namespace);
        $this->assertEquals($returnValue, $pool->deleteItems([$key0, $key1]));
    }

    #[DataProvider('invalidIterableKeyProvider')]
    public function testDeleteItemsRejectsNonStringKeys(mixed $key): void
    {
        $pool = new NamespacedCachePool($this->getHierarchyCacheStub(), 'ns');

        $this->expectException(\Psr\Cache\InvalidArgumentException::class);

        $pool->deleteItems([$key]);
    }

    public function testSave()
    {
        $namespace = 'ns';
        $key = 'key';
        $item = $this->createMock(CacheItemInterface::class);
        $returnValue = true;

        $stub = $this->getHierarchyCacheStub();
        $stub->expects($this->once())->method('getItem')->with('|'.$namespace.'|'.$key)->willReturn($item);
        $stub->expects($this->once())->method('save')->with($item)->willReturn($returnValue);

        $pool = new NamespacedCachePool($stub, $namespace);
        $this->assertEquals($returnValue, $pool->save($pool->getItem($key)));
    }

    public function testSaveDeferred()
    {
        $namespace = 'ns';
        $key = 'key';
        $item = $this->createMock(CacheItemInterface::class);
        $returnValue = true;

        $stub = $this->getHierarchyCacheStub();
        $stub->expects($this->once())->method('getItem')->with('|'.$namespace.'|'.$key)->willReturn($item);
        $stub->expects($this->once())->method('saveDeferred')->with($item)->willReturn($returnValue);

        $pool = new NamespacedCachePool($stub, $namespace);
        $this->assertEquals($returnValue, $pool->saveDeferred($pool->getItem($key)));
    }

    public function testSaveRejectsItemsFromAnotherPool(): void
    {
        $pool = new NamespacedCachePool($this->getHierarchyCacheStub(), 'first');
        $otherItem = $this->createMock(CacheItemInterface::class);
        $otherStub = $this->getHierarchyCacheStub();
        $otherStub->method('getItem')->with('|second|key')->willReturn($otherItem);
        $otherPool = new NamespacedCachePool($otherStub, 'second');

        $this->expectException(\Psr\Cache\InvalidArgumentException::class);

        $pool->save($otherPool->getItem('key'));
    }

    public function testSaveDeferredRejectsItemsFromAnotherPool(): void
    {
        $pool = new NamespacedCachePool($this->getHierarchyCacheStub(), 'first');
        $otherItem = $this->createMock(CacheItemInterface::class);
        $otherStub = $this->getHierarchyCacheStub();
        $otherStub->method('getItem')->with('|second|key')->willReturn($otherItem);
        $otherPool = new NamespacedCachePool($otherStub, 'second');

        $this->expectException(\Psr\Cache\InvalidArgumentException::class);

        $pool->saveDeferred($otherPool->getItem('key'));
    }

    public function testCommit()
    {
        $namespace = 'ns';
        $returnValue = true;

        $stub = $this->getHierarchyCacheStub();
        $stub->expects($this->once())->method('commit')->willReturn($returnValue);

        $pool = new NamespacedCachePool($stub, $namespace);
        $this->assertEquals($returnValue, $pool->commit());
    }
}
