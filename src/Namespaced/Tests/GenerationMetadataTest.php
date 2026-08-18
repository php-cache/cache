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
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class GenerationMetadataTest extends TestCase
{
    public function testGenerationMetadataProbesPastAnOccupiedBackendKey()
    {
        $backend = new ArrayCachePool();
        $collisionKey = 'ns.g.'.sha1('|namespace');
        $probeKey = 'ns.g.'.sha1("|namespace\0".'1');
        $this->assertTrue($backend->save($backend->getItem($collisionKey)->set('outside value')));

        $pool = new NamespacedCachePool(new NonHierarchicalCachePool($backend), 'namespace');
        $this->assertTrue($pool->save($pool->getItem('key')->set('namespaced value')));

        $this->assertSame('outside value', $backend->getItem($collisionKey)->get());
        $this->assertTrue($backend->hasItem($probeKey));
        $this->assertSame('namespaced value', (new NamespacedCachePool(new NonHierarchicalCachePool($backend), 'namespace'))->getItem('key')->get());

        $this->assertTrue($pool->clear());
        $this->assertSame('outside value', $backend->getItem($collisionKey)->get());
        $this->assertFalse($pool->hasItem('key'));
    }

    public function testGenerationProbeDoesNotMoveWhenAnEarlierCollisionDisappears()
    {
        $backend = new ArrayCachePool();
        $collisionKey = 'ns.g.'.sha1('|namespace');
        $probeKey = 'ns.g.'.sha1("|namespace\0".'1');
        $this->assertTrue($backend->save($backend->getItem($collisionKey)->set('outside value')));

        $pool = new NamespacedCachePool(new NonHierarchicalCachePool($backend), 'namespace');
        $this->assertTrue($pool->save($pool->getItem('key')->set('namespaced value')));
        $this->assertTrue($backend->hasItem($probeKey));

        $this->assertTrue($backend->deleteItem($collisionKey));
        $this->assertSame('namespaced value', $pool->getItem('key')->get());
        $this->assertFalse($backend->hasItem($collisionKey));

        $this->assertTrue($backend->save($backend->getItem($collisionKey)->set('replacement value')));
        $this->assertSame('namespaced value', $pool->getItem('key')->get());
    }
}

final class NonHierarchicalCachePool implements CacheItemPoolInterface
{
    public function __construct(private readonly CacheItemPoolInterface $pool)
    {
    }

    public function getItem(string $key): CacheItemInterface
    {
        return $this->pool->getItem($key);
    }

    public function getItems(array $keys = []): iterable
    {
        return $this->pool->getItems($keys);
    }

    public function hasItem(string $key): bool
    {
        return $this->pool->hasItem($key);
    }

    public function clear(): bool
    {
        return $this->pool->clear();
    }

    public function deleteItem(string $key): bool
    {
        return $this->pool->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->pool->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->pool->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->pool->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->pool->commit();
    }
}
