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

use Cache\Adapter\Common\Exception\CachePoolException;
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

    public function testConstructor()
    {
        $this->assertInstanceOf(DoctrineCachePool::class, $this->pool);
        $this->assertInstanceOf(CacheItemPoolInterface::class, $this->pool);
    }

    public function testGetCache()
    {
        $this->assertInstanceOf(Cache::class, $this->pool->getCache());
        $this->assertEquals($this->mockDoctrine, $this->pool->getCache());
    }

    #[DataProvider('invalidPayloads')]
    public function testCorruptPayloadIsACacheMiss(mixed $payload)
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
        yield 'incomplete class' => [str_replace('stdClass', 'GoneType', serialize([true, new \stdClass(), [], null]))];
    }

    public function testCorruptTagListIsIgnored()
    {
        $tagKey = 'tag!'.substr(hash('sha256', 'corrupt'), 0, 60);
        $tagVersionKey = 'tagv!'.substr(hash('sha256', 'corrupt'), 0, 59);
        $this->mockDoctrine->shouldReceive('fetch')->once()->with($tagKey)->andReturn(42);
        $this->mockDoctrine->shouldReceive('delete')->once()->with($tagVersionKey)->andReturn(true);
        $this->mockDoctrine->shouldReceive('delete')->once()->with($tagKey)->andReturn(true);

        self::assertTrue($this->pool->invalidateTag('corrupt'));
    }

    public function testBackendFetchExceptionIsNotTreatedAsCacheMiss()
    {
        $backendException = new \RuntimeException('backend failed');
        $this->mockDoctrine->shouldReceive('fetch')->once()->with('key')->andThrow($backendException);

        try {
            $this->pool->getItem('key')->isHit();
            self::fail('The backend exception was not propagated.');
        } catch (CachePoolException $exception) {
            self::assertSame($backendException, $exception->getPrevious());
        }
    }

    public function testInvalidatingAnExistingTagReportsABackendFailure()
    {
        $tagKey = 'tag!'.substr(hash('sha256', 'tag'), 0, 60);
        $tagVersionKey = 'tagv!'.substr(hash('sha256', 'tag'), 0, 59);
        $this->mockDoctrine->shouldReceive('fetch')->once()->with($tagKey)->andReturn([]);
        $this->mockDoctrine->shouldReceive('delete')->once()->with($tagVersionKey)->andReturn(true);
        $this->mockDoctrine->shouldReceive('delete')->once()->with($tagKey)->andReturn(false);
        $this->mockDoctrine->shouldReceive('contains')->once()->with($tagKey)->andReturn(true);

        self::assertFalse($this->pool->invalidateTag('tag'));
    }

    public function testDeletingAMissingItemIsSuccessful()
    {
        $pool = new DoctrineCachePool(new InMemoryDoctrineCache());

        self::assertTrue($pool->deleteItem('missing'));
    }

    public function testDeletingAnExistingItemReportsABackendFailure()
    {
        $cache = new InMemoryDoctrineCache();
        $pool = new DoctrineCachePool($cache);
        self::assertTrue($pool->save($pool->getItem('key')->set('value')));
        $cache->failDeletes = true;

        self::assertFalse($pool->deleteItem('key'));
        self::assertTrue($pool->hasItem('key'));
    }

    public function testInvalidatingAMissingTagIsSuccessful()
    {
        $pool = new DoctrineCachePool(new InMemoryDoctrineCache());

        self::assertTrue($pool->invalidateTag('missing'));
    }

    public function testClear()
    {
        $this->assertFalse($this->pool->clear());

        $cache = m::mock(Cache::class.','.FlushableCache::class);
        $cache->shouldReceive('flushAll')->andReturn(true);

        $newPool = new DoctrineCachePool($cache);
        $this->assertTrue($newPool->clear());
    }
}
