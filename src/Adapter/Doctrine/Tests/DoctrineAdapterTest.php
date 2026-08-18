<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Doctrine\Tests;

use Cache\Adapter\Doctrine\DoctrineCachePool;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\FlushableCache;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 */
class DoctrineAdapterTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private DoctrineCachePool $pool;

    private MockInterface&Cache $mockDoctrine;

    protected function setUp(): void
    {
        $this->mockDoctrine = m::mock(Cache::class);

        $this->pool = new DoctrineCachePool($this->mockDoctrine);
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(DoctrineCachePool::class, $this->pool);
        $this->assertInstanceOf(CacheItemPoolInterface::class, $this->pool);
    }

    public function testGetCache(): void
    {
        $this->assertInstanceOf(Cache::class, $this->pool->getCache());
        $this->assertEquals($this->mockDoctrine, $this->pool->getCache());
    }

    #[DataProvider('invalidPayloads')]
    public function testCorruptPayloadIsACacheMiss(mixed $payload): void
    {
        $this->mockDoctrine->shouldReceive('fetch')->once()->with('corrupt')->andReturn($payload);

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
    }

    public function testCorruptTagListIsIgnored(): void
    {
        $this->mockDoctrine->shouldReceive('fetch')->once()->with('tag!corrupt')->andReturn(42);
        $this->mockDoctrine->shouldReceive('delete')->once()->with('tag!corrupt')->andReturn(true);

        self::assertTrue($this->pool->invalidateTag('corrupt'));
    }

    public function testInvalidatingAnExistingTagReportsABackendFailure(): void
    {
        $this->mockDoctrine->shouldReceive('fetch')->once()->with('tag!tag')->andReturn([]);
        $this->mockDoctrine->shouldReceive('delete')->once()->with('tag!tag')->andReturn(false);
        $this->mockDoctrine->shouldReceive('contains')->once()->with('tag!tag')->andReturn(true);

        self::assertFalse($this->pool->invalidateTag('tag'));
    }

    public function testDeletingAMissingItemIsSuccessful(): void
    {
        $pool = new DoctrineCachePool(new InMemoryDoctrineCache());

        self::assertTrue($pool->deleteItem('missing'));
    }

    public function testDeletingAnExistingItemReportsABackendFailure(): void
    {
        $cache = new InMemoryDoctrineCache();
        $pool = new DoctrineCachePool($cache);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')));
        $cache->failDeletes = true;

        self::assertFalse($pool->deleteItem('key'));
        self::assertTrue($pool->hasItem('key'));
    }

    public function testInvalidatingAMissingTagIsSuccessful(): void
    {
        $pool = new DoctrineCachePool(new InMemoryDoctrineCache());

        self::assertTrue($pool->invalidateTag('missing'));
    }

    public function testClear(): void
    {
        $this->assertFalse($this->pool->clear());

        $cache = m::mock(Cache::class.','.FlushableCache::class);
        $cache->shouldReceive('flushAll')->andReturn(true);

        $newPool = new DoctrineCachePool($cache);
        $this->assertTrue($newPool->clear());
    }
}
