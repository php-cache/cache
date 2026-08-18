<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Prefixed\Tests;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Prefixed\PrefixedCachePool;
use Cache\TagInterop\TaggableCacheItemInterface;
use Cache\TagInterop\TaggableCacheItemPoolInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class PrefixedCachePoolTest extends TestCase
{
    public function testCreatePreservesNativeTagSupport()
    {
        $pool = PrefixedCachePool::create(new ArrayCachePool(), 'prefix.');

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

    public function testCreateDoesNotAdvertiseTagsForAGenericPool()
    {
        $pool = PrefixedCachePool::create($this->getCacheStub(), 'prefix.');

        $this->assertNotInstanceOf(TaggableCacheItemPoolInterface::class, $pool);
    }

    public function testReservedCharactersInPrefixAreEncoded()
    {
        $backend = new ArrayCachePool();
        $pool = new PrefixedCachePool($backend, '{}()/\\@:%|!');

        $this->assertTrue($pool->save($pool->getItem('key')->set('value')));
        $this->assertTrue($backend->hasItem('_x7B__x7D__x28__x29__x2F__x5C__x40__x3A__x25__x7C__x21_key'));
        $this->assertSame('value', $pool->getItem('key')->get());
    }

    public function testEncodedPrefixUsesOnlyPortablePsrCharacters()
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('getKey')->willReturn('_x7B_key');
        $backend = $this->createMock(CacheItemPoolInterface::class);
        $backend->expects($this->once())
            ->method('getItem')
            ->with($this->callback(static fn (string $key): bool => 1 === preg_match('/^[A-Za-z0-9_.]+$/D', $key)))
            ->willReturn($item);

        $this->assertSame('key', (new PrefixedCachePool($backend, '{'))->getItem('key')->getKey());
    }

    public function testLiteralEncodingMarkerDoesNotCollideWithEncodedPrefix()
    {
        $backend = new ArrayCachePool();
        $encoded = new PrefixedCachePool($backend, '%');
        $literal = new PrefixedCachePool($backend, '_x25_');

        $this->assertTrue($encoded->save($encoded->getItem('key')->set('encoded')));
        $this->assertTrue($literal->save($literal->getItem('key')->set('literal')));

        $this->assertSame('encoded', $encoded->getItem('key')->get());
        $this->assertSame('literal', $literal->getItem('key')->get());
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
     * @return \PHPUnit_Framework_MockObject_MockObject|CacheItemPoolInterface
     */
    private function getCacheStub()
    {
        return $this->getMockBuilder(CacheItemPoolInterface::class)->onlyMethods(
            ['getItem', 'getItems', 'hasItem', 'clear', 'deleteItem', 'deleteItems', 'save', 'saveDeferred', 'commit']
        )->getMock();
    }

    public function testGetItem()
    {
        $prefix = 'ns';
        $key = 'key';
        $returnValue = $this->createMock(CacheItemInterface::class);
        $returnValue->method('getKey')->willReturn($prefix.$key);

        $stub = $this->getCacheStub();
        $stub->expects($this->once())->method('getItem')->with($prefix.$key)->willReturn($returnValue);

        $pool = new PrefixedCachePool($stub, $prefix);
        $this->assertSame($key, $pool->getItem($key)->getKey());
    }

    public function testGetItems()
    {
        $prefix = 'ns';
        $key0 = 'key0';
        $key1 = 'key1';
        $item0 = $this->createMock(CacheItemInterface::class);
        $item1 = $this->createMock(CacheItemInterface::class);
        $item0->method('getKey')->willReturn($prefix.$key0);
        $item1->method('getKey')->willReturn($prefix.$key1);
        $returnValue = [$prefix.$key0 => $item0, $prefix.$key1 => $item1];

        $stub = $this->getCacheStub();
        $stub->expects($this->once())->method('getItems')->with([$prefix.$key0, $prefix.$key1])->willReturn($returnValue);

        $pool = new PrefixedCachePool($stub, $prefix);
        $items = iterator_to_array($pool->getItems([$key0, $key1]));

        $this->assertSame([$key0, $key1], array_keys($items));
        $this->assertSame($key0, $items[$key0]->getKey());
        $this->assertSame($key1, $items[$key1]->getKey());
    }

    public function testGetItemsPreservesNumericStringKeysWithAnEmptyPrefix()
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('getKey')->willReturn('123');

        $stub = $this->getCacheStub();
        $stub->expects($this->once())->method('getItems')->with(['123'])->willReturn([123 => $item]);

        $keys = [];
        foreach ((new PrefixedCachePool($stub, ''))->getItems(['123']) as $key => $returnedItem) {
            $keys[] = $key;
            $this->assertSame('123', $returnedItem->getKey());
        }

        $this->assertSame(['123'], $keys);
    }

    #[DataProvider('invalidIterableKeyProvider')]
    public function testGetItemsRejectsNonStringKeys(mixed $key)
    {
        $pool = new PrefixedCachePool($this->getCacheStub(), 'ns');

        $this->expectException(\Psr\Cache\InvalidArgumentException::class);

        $pool->getItems([$key]);
    }

    public function testHasItem()
    {
        $prefix = 'ns';
        $key = 'key';
        $returnValue = true;

        $stub = $this->getCacheStub();
        $stub->expects($this->once())->method('hasItem')->with($prefix.$key)->willReturn($returnValue);

        $pool = new PrefixedCachePool($stub, $prefix);
        $this->assertEquals($returnValue, $pool->hasItem($key));
    }

    public function testClear()
    {
        $prefix = 'ns';
        $key = 'key';
        $returnValue = true;

        $stub = $this->getCacheStub();
        $stub->expects($this->once())->method('clear')->willReturn($returnValue);

        $pool = new PrefixedCachePool($stub, $prefix);
        $this->assertEquals($returnValue, $pool->clear());
    }

    public function testDeleteItem()
    {
        $prefix = 'ns';
        $key = 'key';
        $returnValue = true;

        $stub = $this->getCacheStub();
        $stub->expects($this->once())->method('deleteItem')->with($prefix.$key)->willReturn($returnValue);

        $pool = new PrefixedCachePool($stub, $prefix);
        $this->assertEquals($returnValue, $pool->deleteItem($key));
    }

    public function testDeleteItems()
    {
        $prefix = 'ns';
        $key0 = 'key0';
        $key1 = 'key1';
        $returnValue = true;

        $stub = $this->getCacheStub();
        $stub->expects($this->once())->method('deleteItems')->with([$prefix.$key0, $prefix.$key1])->willReturn($returnValue);

        $pool = new PrefixedCachePool($stub, $prefix);
        $this->assertEquals($returnValue, $pool->deleteItems([$key0, $key1]));
    }

    #[DataProvider('invalidIterableKeyProvider')]
    public function testDeleteItemsRejectsNonStringKeys(mixed $key)
    {
        $pool = new PrefixedCachePool($this->getCacheStub(), 'ns');

        $this->expectException(\Psr\Cache\InvalidArgumentException::class);

        $pool->deleteItems([$key]);
    }

    public function testSave()
    {
        $prefix = 'ns';
        $key = 'key';
        $item = $this->createMock(CacheItemInterface::class);
        $returnValue = true;

        $stub = $this->getCacheStub();
        $stub->expects($this->once())->method('getItem')->with($prefix.$key)->willReturn($item);
        $stub->expects($this->once())->method('save')->with($item)->willReturn($returnValue);

        $pool = new PrefixedCachePool($stub, $prefix);
        $this->assertEquals($returnValue, $pool->save($pool->getItem($key)));
    }

    public function testSaveDeffered()
    {
        $prefix = 'ns';
        $key = 'key';
        $item = $this->createMock(CacheItemInterface::class);
        $returnValue = true;

        $stub = $this->getCacheStub();
        $stub->expects($this->once())->method('getItem')->with($prefix.$key)->willReturn($item);
        $stub->expects($this->once())->method('saveDeferred')->with($item)->willReturn($returnValue);

        $pool = new PrefixedCachePool($stub, $prefix);
        $this->assertEquals($returnValue, $pool->saveDeferred($pool->getItem($key)));
    }

    public function testSaveRejectsItemsFromAnotherPool()
    {
        $pool = new PrefixedCachePool($this->getCacheStub(), 'first.');
        $otherItem = $this->createMock(CacheItemInterface::class);
        $otherStub = $this->getCacheStub();
        $otherStub->method('getItem')->with('second.key')->willReturn($otherItem);
        $otherPool = new PrefixedCachePool($otherStub, 'second.');

        $this->expectException(\Psr\Cache\InvalidArgumentException::class);

        $pool->save($otherPool->getItem('key'));
    }

    public function testSaveDeferredRejectsItemsFromAnotherPool()
    {
        $pool = new PrefixedCachePool($this->getCacheStub(), 'first.');
        $otherItem = $this->createMock(CacheItemInterface::class);
        $otherStub = $this->getCacheStub();
        $otherStub->method('getItem')->with('second.key')->willReturn($otherItem);
        $otherPool = new PrefixedCachePool($otherStub, 'second.');

        $this->expectException(\Psr\Cache\InvalidArgumentException::class);

        $pool->saveDeferred($otherPool->getItem('key'));
    }

    public function testCommit()
    {
        $prefix = 'ns';
        $returnValue = true;

        $stub = $this->getCacheStub();
        $stub->expects($this->once())->method('commit')->willReturn($returnValue);

        $pool = new PrefixedCachePool($stub, $prefix);
        $this->assertEquals($returnValue, $pool->commit());
    }
}
