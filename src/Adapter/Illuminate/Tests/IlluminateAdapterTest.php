<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Illuminate\Tests;

use Cache\Adapter\Illuminate\IlluminateCachePool;
use Illuminate\Contracts\Cache\Store;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

class IlluminateAdapterTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private IlluminateCachePool $pool;

    private MockInterface&Store $mockStore;

    protected function setUp(): void
    {
        $this->mockStore = m::mock(Store::class);
        $this->pool = new IlluminateCachePool($this->mockStore);
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(IlluminateCachePool::class, $this->pool);
        $this->assertInstanceOf(CacheItemPoolInterface::class, $this->pool);
    }

    #[DataProvider('invalidPayloads')]
    public function testCorruptPayloadIsACacheMiss(mixed $payload)
    {
        $this->mockStore->shouldReceive('get')->once()->with('corrupt')->andReturn($payload);

        self::assertFalse($this->pool->getItem('corrupt')->isHit());
    }

    public static function invalidPayloads(): iterable
    {
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'integer' => [42];
        yield 'array' => [[]];
        yield 'malformed serialization' => ['not serialized'];
        yield 'invalid hit marker' => [serialize([false, 'value', [], null])];
        yield 'invalid tags' => [serialize([true, 'value', [42], null])];
        yield 'invalid expiration' => [serialize([true, 'value', [], 'tomorrow'])];
        yield 'incomplete class' => [str_replace('stdClass', 'GoneType', serialize([true, new \stdClass(), [], null]))];
    }

    public function testTtlIsPassedToIlluminateInSeconds()
    {
        $this->mockStore->shouldReceive('get')->once()->with('ttl')->andReturn(null);
        $this->mockStore
            ->shouldReceive('put')
            ->once()
            ->with('ttl', m::type('string'), 120)
            ->andReturn(true);

        self::assertTrue($this->pool->set('ttl', 'value', 120));
    }

    public function testItemWithoutTtlUsesForeverStorage()
    {
        $this->mockStore->shouldReceive('get')->once()->with('forever')->andReturn(null);
        $this->mockStore
            ->shouldReceive('forever')
            ->once()
            ->with('forever', m::type('string'))
            ->andReturn(true);

        self::assertTrue($this->pool->set('forever', 'value'));
    }

    public function testFailedTtlWriteIsReported()
    {
        $this->mockStore->shouldReceive('get')->once()->with('ttl')->andReturn(null);
        $this->mockStore->shouldReceive('put')->once()->andReturn(false);

        self::assertFalse($this->pool->set('ttl', 'value', 120));
    }

    public function testFailedForeverWriteIsReported()
    {
        $this->mockStore->shouldReceive('get')->once()->with('forever')->andReturn(null);
        $this->mockStore->shouldReceive('forever')->once()->andReturn(false);

        self::assertFalse($this->pool->set('forever', 'value'));
    }

    public function testHierarchyCounterUsesForeverStorage()
    {
        $pool = new class($this->mockStore) extends IlluminateCachePool {
            public function clearObject(string $key): bool
            {
                return $this->clearOneObjectFromCache($key);
            }
        };

        $this->mockStore->shouldReceive('get')->times(4)->andReturn(null);
        $this->mockStore->shouldReceive('forever')->once()->with(m::type('string'), 0)->andReturn(true);
        $this->mockStore->shouldReceive('increment')->once()->with(m::type('string'))->andReturn(1);

        self::assertTrue($pool->clearObject('|parent'));
    }

    public function testDeletingAParentWithoutAStoredItemInvalidatesItsDescendants()
    {
        $store = new \Illuminate\Cache\ArrayStore();
        $pool = new IlluminateCachePool($store);
        self::assertTrue($pool->save($pool->getItem('|parent|child')->set('value')));

        self::assertTrue($pool->deleteItem('|parent'));
        self::assertFalse($pool->hasItem('|parent|child'));
    }

    public function testHierarchyDeleteReportsFailedCounterInitialization()
    {
        $pool = new class($this->mockStore) extends IlluminateCachePool {
            public function clearObject(string $key): bool
            {
                return $this->clearOneObjectFromCache($key);
            }
        };

        $this->mockStore->shouldReceive('get')->times(4)->andReturn(null);
        $this->mockStore->shouldReceive('forever')->once()->andReturn(false);
        $this->mockStore->shouldReceive('increment')->once()->andReturn(1);

        self::assertFalse($pool->clearObject('|parent'));
    }

    public function testHierarchyDeleteReportsFailedCounterIncrement()
    {
        $pool = new class($this->mockStore) extends IlluminateCachePool {
            public function clearObject(string $key): bool
            {
                return $this->clearOneObjectFromCache($key);
            }
        };

        $this->mockStore->shouldReceive('get')->times(4)->andReturn(null);
        $this->mockStore->shouldReceive('forever')->once()->andReturn(true);
        $this->mockStore->shouldReceive('increment')->once()->andReturn(false);

        self::assertFalse($pool->clearObject('|parent'));
    }

    public function testDeleteReportsARealBackendFailure()
    {
        $payload = serialize([true, 'value', [], null]);
        $this->mockStore->shouldReceive('get')->times(3)->with('key')->andReturn($payload);
        $this->mockStore->shouldReceive('forget')->once()->with('key')->andReturn(false);

        self::assertFalse($this->pool->deleteItem('key'));
    }

    public function testCorruptTagListIsIgnored()
    {
        $this->mockStore->shouldReceive('get')->twice()->with('tag!corrupt')->andReturn([42]);
        $this->mockStore->shouldReceive('forget')->once()->with('tag!corrupt')->andReturn(true);

        self::assertTrue($this->pool->invalidateTag('corrupt'));
    }
}
