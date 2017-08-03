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
use Cache\Adapter\PHPArray\ArrayCachePool;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class ChainPoolTest.
 * @covers \Cache\Adapter\Chain\CachePoolChain
 */
class CachePoolChainTest extends \PHPUnit_Framework_TestCase
{
    public function testGetItemStoreToPrevious()
    {
        $firstPool  = new ArrayCachePool();
        $secondPool = new ArrayCachePool();
        $chainPool  = new CachePoolChain([$firstPool, $secondPool]);

        $key  = 'test_key';
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

    public function testGetItemsStoreToPrevious()
    {
        $firstPool  = new ArrayCachePool();
        $secondPool = new ArrayCachePool();
        $chainPool  = new CachePoolChain([$firstPool, $secondPool]);

        $key  = 'test_key';
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

        $items = $chainPool->getItems([$key, $key2]);

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

    public function testGetItemsWithEmptyCache()
    {
        $firstPool = new ArrayCachePool();
        $secondPool = new ArrayCachePool();
        $chainPool = new CachePoolChain([$firstPool, $secondPool]);
    
        $key = 'test_key';
        $key2 = 'test_key2';
    
        $items = $chainPool->getItems([$key, $key2]);
    
        $this->assertArrayHasKey($key, $items);
        $this->assertArrayHasKey($key2, $items);
    
        $this->assertFalse($items[$key]->isHit());
        $this->assertFalse($items[$key2]->isHit());
    }
    
    public function testWritesOneItemToMultiplePools()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
        
        $firstPool = new ArrayCachePool();
        self::assertFalse($firstPool->hasItem($key));
        
        $secondPool = new ArrayCachePool();
        self::assertFalse($secondPool->hasItem($key));
        
        $chain = new CachePoolChain([
            $firstPool,
            $secondPool,
        ]);
        
        $result = $chain->save(new CacheItem($key, true, $value));
        
        self::assertTrue($result);
        
        self::assertTrue($firstPool->hasItem($key));
        self::assertTrue($secondPool->hasItem($key));
    }
    
    public function testReadsOneItemFromFirstPool()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
        
        $firstPool = new ArrayCachePool();
        $firstPool->save(new CacheItem($key, true, $value));
        self::assertTrue($firstPool->hasItem($key));
        
        $secondPool = new ArrayCachePool();
        self::assertFalse($secondPool->hasItem($key));
        
        $chain = new CachePoolChain([
            $firstPool,
            $secondPool,
        ]);
        
        $item = $chain->getItem($key);
        self::assertInstanceOf(CacheItem::class, $item);
        self::assertSame($value, $item->get());
    }
    
    public function testReadsOneItemFromSecondPoolAndRestoresIntoFirstPool()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
        
        $firstPool = new ArrayCachePool();
        self::assertFalse($firstPool->hasItem($key));
        
        $secondPool = new ArrayCachePool();
        $secondPool->save(new CacheItem($key, true, $value));
        self::assertTrue($secondPool->hasItem($key));
        
        $chain = new CachePoolChain([
            $firstPool,
            $secondPool,
        ]);
        
        $item = $chain->getItem($key);
        self::assertInstanceOf(CacheItem::class, $item);
        self::assertSame($value, $item->get());
        
        self::assertTrue($firstPool->hasItem($key));
        self::assertTrue($secondPool->hasItem($key));
    }
    
    /**
     * @expectedException \Psr\Cache\CacheException
     */
    public function testDefaultBehaviorOfSkipOnFailureOption()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
    
        $firstPool = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->getMock();
        $firstPool->method('getItem')
            ->with($key)
            ->willThrowException(new CachePoolException());
    
        $secondPool = new ArrayCachePool();
        $secondPool->save(new CacheItem($key, true, $value));
        self::assertTrue($secondPool->hasItem($key));
    
        $chain = new CachePoolChain([
            $firstPool,
            $secondPool,
        ]);
        
        $chain->getItem($key);
    }
    
    /**
     * @expectedException \Psr\Cache\CacheException
     */
    public function testDisabledBehaviorOfSkipOnFailureOption()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
    
        $firstPool = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->getMock();
        $firstPool->method('getItem')
            ->with($key)
            ->willThrowException(new CachePoolException());
    
        $secondPool = new ArrayCachePool();
        $secondPool->save(new CacheItem($key, true, $value));
        self::assertTrue($secondPool->hasItem($key));
    
        $pools = [
            $firstPool,
            $secondPool,
        ];
    
        $options = [
            'skip_on_failure' => false,
        ];
        
        $chain = new CachePoolChain($pools, $options);
    
        $chain->getItem($key);
    }
    
    public function testEnabledBehaviorOfSkipOnFailureOption()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
    
        $firstPool = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->getMock();
        $firstPool->method('getItem')
            ->with($key)
            ->willThrowException(new CachePoolException());
    
        $secondPool = new ArrayCachePool();
        $secondPool->save(new CacheItem($key, true, $value));
        self::assertTrue($secondPool->hasItem($key));
    
        $pools = [
            $firstPool,
            $secondPool,
        ];
    
        $options = [
            'skip_on_failure' => true,
        ];
    
        $chain = new CachePoolChain($pools, $options);
    
        $item = $chain->getItem($key);
        
        self::assertSame($value, $item->get());
    }
    
    public function testReadsManyItems()
    {
        $firstKey = 'FooBar';
        $firstValue = 'FizBuz';
    
        $secondKey = 'Alice';
        $secondValue = 'Bob';
    
        $pool = new ArrayCachePool();
        $pool->save(new CacheItem($firstKey, true, $firstValue));
        self::assertTrue($pool->hasItem($firstKey));
        
        $pool->save(new CacheItem($secondKey, true, $secondValue));
        self::assertTrue($pool->hasItem($secondKey));
        
        $chain = new CachePoolChain([
            $pool,
        ]);
        
        $items = $chain->getItems([
            $firstKey,
            $secondKey,
        ]);
        
        self::assertInstanceOf(CacheItem::class, $firstItem = $items[$firstKey]);
        self::assertSame($firstValue, $firstItem->get());
        
        self::assertInstanceOf(CacheItem::class, $secondItem = $items[$secondKey]);
        self::assertSame($secondValue, $secondItem->get());
    }
    
    public function testReadsManyMissedItems()
    {
        $pool = new ArrayCachePool();
        
        $chain = new CachePoolChain([
            $pool,
        ]);
        
        $items = $chain->getItems([
            'FooBar',
            'FizBuz',
        ]);
        
        self::assertInstanceOf(CacheItem::class, $firstItem = $items['FooBar']);
        self::assertNull($firstItem->get());
        
        self::assertInstanceOf(CacheItem::class, $secondItem = $items['FizBuz']);
        self::assertNull($secondItem->get());
    }
    
    public function testHasItem()
    {
        $firstPool = new ArrayCachePool();
        self::assertFalse($firstPool->hasItem('FooBar'));
        
        $secondPool = new ArrayCachePool();
        $secondPool->save(new CacheItem('FooBar', true, 'FizBuz'));
        self::assertTrue($secondPool->hasItem('FooBar'));
        
        $chain = new CachePoolChain([
            $firstPool,
            $secondPool,
        ]);
        
        self::assertTrue($chain->hasItem('FooBar'));
    }
    
    public function testNotHasItem()
    {
        $firstPool = new ArrayCachePool();
        self::assertFalse($firstPool->hasItem('FooBar'));
    
        $secondPool = new ArrayCachePool();
        self::assertFalse($secondPool->hasItem('FooBar'));
        
        $chain = new CachePoolChain([
            $firstPool,
            $secondPool,
        ]);
        
        self::assertFalse($chain->hasItem('FooBar'));
    }
    
    public function testClear()
    {
        $firstPool = new ArrayCachePool();
        self::assertFalse($firstPool->hasItem('FooBar'));
    
        $secondPool = new ArrayCachePool();
        $secondPool->save(new CacheItem('FooBar', true, 'FizBuz'));
        self::assertTrue($secondPool->hasItem('FooBar'));
    
        $chain = new CachePoolChain([
            $firstPool,
            $secondPool,
        ]);
    
        self::assertTrue($chain->hasItem('FooBar'));
        
        self::assertTrue($chain->clear());
        
        self::assertFalse($chain->hasItem('FooBar'));
    }
    
    public function testDeleteItem()
    {
        $firstKey = 'FooBar';
        $firstValue = 'FizBuz';
    
        $secondKey = 'Alice';
        $secondValue = 'Bob';
    
        $firstPool = new ArrayCachePool();
        $firstPool->save(new CacheItem($firstKey, true, $firstValue));
        self::assertTrue($firstPool->hasItem($firstKey));
        
        $firstPool->save(new CacheItem($secondKey, true, $secondValue));
        self::assertTrue($firstPool->hasItem($secondKey));
    
        $secondPool = new ArrayCachePool();
        $secondPool->save(new CacheItem($firstKey, true, $firstValue));
        self::assertTrue($secondPool->hasItem($firstKey));
    
        $secondPool->save(new CacheItem($secondKey, true, $secondValue));
        self::assertTrue($secondPool->hasItem($secondKey));
    
        $chain = new CachePoolChain([
            $firstPool,
            $secondPool,
        ]);
    
        self::assertTrue($chain->hasItem($firstKey));
        self::assertTrue($chain->hasItem($secondKey));
        
        self::assertTrue($chain->deleteItem($firstKey));
        
        self::assertFalse($chain->hasItem($firstKey));
        self::assertTrue($chain->hasItem($secondKey));
    }
    
    public function testDeleteItems()
    {
        $firstKey = 'FooBar';
        $firstValue = 'FizBuz';
    
        $secondKey = 'Alice';
        $secondValue = 'Bob';
    
        $firstPool = new ArrayCachePool();
        $firstPool->save(new CacheItem($firstKey, true, $firstValue));
        self::assertTrue($firstPool->hasItem($firstKey));
    
        $firstPool->save(new CacheItem($secondKey, true, $secondValue));
        self::assertTrue($firstPool->hasItem($secondKey));
    
        $secondPool = new ArrayCachePool();
        $secondPool->save(new CacheItem($firstKey, true, $firstValue));
        self::assertTrue($secondPool->hasItem($firstKey));
    
        $secondPool->save(new CacheItem($secondKey, true, $secondValue));
        self::assertTrue($secondPool->hasItem($secondKey));
    
        $chain = new CachePoolChain([
            $firstPool,
            $secondPool,
        ]);
    
        self::assertTrue($chain->hasItem($firstKey));
        self::assertTrue($chain->hasItem($secondKey));
    
        self::assertTrue($chain->deleteItems([
            $firstKey,
            $secondKey,
        ]));
    
        self::assertFalse($chain->hasItem($firstKey));
        self::assertFalse($chain->hasItem($secondKey));
    }
    
    public function testDeferredSaveWritesOneItemToMultiplePools()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
        
        $firstPool = new ArrayCachePool();
        self::assertFalse($firstPool->hasItem($key));
        
        $secondPool = new ArrayCachePool();
        self::assertFalse($secondPool->hasItem($key));
        
        $chain = new CachePoolChain([
            $firstPool,
            $secondPool,
        ]);
    
        self::assertTrue($chain->saveDeferred(new CacheItem($key, true, $value)));
        
        self::assertTrue($chain->hasItem($key));
        self::assertTrue($firstPool->hasItem($key));
        self::assertTrue($secondPool->hasItem($key));
        
        self::assertTrue($chain->commit());
    
        self::assertTrue($chain->hasItem($key));
        self::assertTrue($firstPool->hasItem($key));
        self::assertTrue($secondPool->hasItem($key));
    }
}
