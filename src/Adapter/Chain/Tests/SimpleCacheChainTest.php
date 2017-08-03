<?php

namespace Cache\Adapter\Chain\Tests;

use Cache\Adapter\Chain\SimpleCacheChain;
use Cache\Adapter\PHPArray\SimpleArrayCache;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @covers \Cache\Adapter\Chain\SimpleCacheChain
 */
class SimpleCacheChainTest extends \PHPUnit_Framework_TestCase
{
    public function testWritesOneItemToMultiplePools()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
        
        $firstPool = new SimpleArrayCache();
        self::assertFalse($firstPool->has($key));
        
        $secondPool = new SimpleArrayCache();
        self::assertFalse($secondPool->has($key));
        
        $chain = new SimpleCacheChain([
            $firstPool,
            $secondPool,
        ]);
        
        $result = $chain->set($key, $value);
        
        self::assertTrue($result);
        
        self::assertTrue($firstPool->has($key));
        self::assertTrue($secondPool->has($key));
    }
    
    public function testReadsOneItemFromFirstPool()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
    
        $firstPool = new SimpleArrayCache([
            $key => $value,
        ]);
        self::assertTrue($firstPool->has($key));
        
        $secondPool = new SimpleArrayCache();
        self::assertFalse($secondPool->has($key));
        
        $chain = new SimpleCacheChain([
            $firstPool,
            $secondPool,
        ]);
        
        $result = $chain->get($key);
        
        self::assertSame($value, $result);
    }
    
    public function testReadsOneItemFromSecondPool()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
        
        $firstPool = new SimpleArrayCache();
        self::assertFalse($firstPool->has($key));
    
        $secondPool = new SimpleArrayCache([
            $key => $value,
        ]);
        self::assertTrue($secondPool->has($key));
        
        $chain = new SimpleCacheChain([
            $firstPool,
            $secondPool,
        ]);
        
        $result = $chain->get($key);
        
        self::assertSame($value, $result);
    }
    
    public function testGetDefault()
    {
        $key = 'FooBar';
        
        $firstPool = new SimpleArrayCache();
        self::assertFalse($firstPool->has($key));
    
        $secondPool = new SimpleArrayCache();
        self::assertFalse($secondPool->has($key));
        
        $chain = new SimpleCacheChain([
            $firstPool,
            $secondPool,
        ]);
        
        $default = new \Exception();
        
        $result = $chain->get($key, $default);
        
        self::assertSame($default, $result);
    }
    
    /**
     * @expectedException \Psr\Cache\CacheException
     */
    public function testDefaultBehaviorOfSkipOnFailureOption()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
        
        $firstPool = $this->getMockBuilder(CacheInterface::class)
            ->getMock();
        $firstPool->method('get')
            ->with($key)
            ->willThrowException(new InvalidArgumentException());
    
        $secondPool = new SimpleArrayCache([
            $key => $value,
        ]);
        self::assertTrue($secondPool->has($key));
        
        $chain = new SimpleCacheChain([
            $firstPool,
            $secondPool,
        ]);
        
        $chain->get($key);
    }
    
    /**
     * @expectedException \Psr\Cache\CacheException
     */
    public function testDisabledBehaviorOfSkipOnFailureOption()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
        
        $firstPool = $this->getMockBuilder(CacheInterface::class)
            ->getMock();
        $firstPool->method('get')
            ->with($key)
            ->willThrowException(new InvalidArgumentException());
    
        $secondPool = new SimpleArrayCache([
            $key => $value,
        ]);
        self::assertTrue($secondPool->has($key));
        
        $pools = [
            $firstPool,
            $secondPool,
        ];
        
        $options = [
            'skip_on_failure' => false,
        ];
        
        $chain = new SimpleCacheChain($pools, $options);
        
        $chain->get($key);
    }
    
    public function testEnabledBehaviorOfSkipOnFailureOption()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
        
        $firstPool = $this->getMockBuilder(CacheInterface::class)
            ->getMock();
        $firstPool->method('get')
            ->with($key)
            ->willThrowException(new InvalidArgumentException());
        
        $secondPool = new SimpleArrayCache([
            $key => $value,
        ]);
        self::assertTrue($secondPool->has($key));
        
        $pools = [
            $firstPool,
            $secondPool,
        ];
        
        $options = [
            'skip_on_failure' => true,
        ];
        
        $chain = new SimpleCacheChain($pools, $options);
        
        $result = $chain->get($key);
        
        self::assertSame($value, $result);
    }
    
    public function testReadsManyItems()
    {
        $firstKey = 'FooBar';
        $firstValue = 'FizBuz';
        
        $secondKey = 'Alice';
        $secondValue = 'Bob';
        
        $pool = new SimpleArrayCache([
            $firstKey => $firstValue,
            $secondKey => $secondValue,
        ]);
        self::assertTrue($pool->has($firstKey));
        self::assertTrue($pool->has($secondKey));
        
        $chain = new SimpleCacheChain([
            $pool,
        ]);
        
        $values = $chain->getMultiple([
            $firstKey,
            $secondKey,
        ]);
        
        self::assertSame($firstValue, $values[$firstKey]);
        self::assertSame($secondValue, $values[$secondKey]);
    }
    
    public function testReadsManyMissedItems()
    {
        $firstKey = 'FooBar';
        $secondKey = 'FizBuz';
    
        $pool = new SimpleArrayCache();
    
        $chain = new SimpleCacheChain([
            $pool,
        ]);
        
        $values = $chain->getMultiple([
            $firstKey,
            $secondKey,
        ]);
        
        self::assertNull($values[$firstKey]);
        self::assertNull($values[$secondKey]);
    }
    
    public function testHasItem()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
        
        $firstPool = new SimpleArrayCache();
        self::assertFalse($firstPool->has($key));
    
        $secondPool = new SimpleArrayCache([
            $key => $value,
        ]);
        self::assertTrue($secondPool->has($key));
        
        $chain = new SimpleCacheChain([
            $firstPool,
            $secondPool,
        ]);
        
        self::assertTrue($chain->has($key));
    }
    
    public function testNotHasItem()
    {
        $key = 'FooBar';
        
        $firstPool = new SimpleArrayCache();
        self::assertFalse($firstPool->has($key));
        
        $secondPool = new SimpleArrayCache();
        self::assertFalse($secondPool->has($key));
        
        $chain = new SimpleCacheChain([
            $firstPool,
            $secondPool,
        ]);
        
        self::assertFalse($chain->has($key));
    }
    
    public function testClear()
    {
        $key = 'FooBar';
        $value = 'FizBuz';
        
        $firstPool = new SimpleArrayCache();
        self::assertFalse($firstPool->has($key));
    
        $secondPool = new SimpleArrayCache([
            $key => $value,
        ]);
        self::assertTrue($secondPool->has($key));
        
        $chain = new SimpleCacheChain([
            $firstPool,
            $secondPool,
        ]);
        
        self::assertTrue($chain->has($key));
        
        self::assertTrue($chain->clear());
        
        self::assertFalse($chain->has($key));
    }
    
    public function testDeleteItem()
    {
        $firstKey = 'FooBar';
        $firstValue = 'FizBuz';
        
        $secondKey = 'Alice';
        $secondValue = 'Bob';
        
        $firstPool = new SimpleArrayCache([
            $firstKey => $firstValue,
            $secondKey => $secondValue,
        ]);
        self::assertTrue($firstPool->has($firstKey));
        self::assertTrue($firstPool->has($secondKey));
        
        $secondPool = new SimpleArrayCache([
            $firstKey => $firstValue,
            $secondKey => $secondValue,
        ]);
        self::assertTrue($secondPool->has($firstKey));
        self::assertTrue($secondPool->has($secondKey));
        
        $chain = new SimpleCacheChain([
            $firstPool,
            $secondPool,
        ]);
        
        self::assertTrue($chain->has($firstKey));
        self::assertTrue($chain->has($secondKey));
        
        self::assertTrue($chain->delete($firstKey));
        
        self::assertFalse($chain->has($firstKey));
        self::assertTrue($chain->has($secondKey));
    }
    
    public function testDeleteItems()
    {
        $firstKey = 'FooBar';
        $firstValue = 'FizBuz';
        
        $secondKey = 'Alice';
        $secondValue = 'Bob';
    
        $firstPool = new SimpleArrayCache([
            $firstKey => $firstValue,
            $secondKey => $secondValue,
        ]);
        self::assertTrue($firstPool->has($firstKey));
        self::assertTrue($firstPool->has($secondKey));
    
        $secondPool = new SimpleArrayCache([
            $firstKey => $firstValue,
            $secondKey => $secondValue,
        ]);
        self::assertTrue($secondPool->has($firstKey));
        self::assertTrue($secondPool->has($secondKey));
        
        $chain = new SimpleCacheChain([
            $firstPool,
            $secondPool,
        ]);
        
        self::assertTrue($chain->has($firstKey));
        self::assertTrue($chain->has($secondKey));
        
        self::assertTrue($chain->deleteMultiple([
            $firstKey,
            $secondKey,
        ]));
        
        self::assertFalse($chain->has($firstKey));
        self::assertFalse($chain->has($secondKey));
    }
    
    /**
     * @expectedException \Cache\Adapter\Chain\Exception\NoPoolAvailableException
     */
    public function testNoPools()
    {
        new SimpleCacheChain([]);
    }
}
