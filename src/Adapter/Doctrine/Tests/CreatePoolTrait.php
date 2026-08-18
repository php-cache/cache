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
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;

final class InMemoryDoctrineCache implements Cache, FlushableCache
{
    /** @var array<array-key, mixed> */
    private array $values = [];

    public bool $failDeletes = false;

    public function fetch($id): mixed
    {
        return $this->values[$id] ?? false;
    }

    public function contains($id): bool
    {
        return array_key_exists($id, $this->values);
    }

    public function save($id, $data, $lifeTime = 0): bool
    {
        $this->values[$id] = $data;

        return true;
    }

    public function delete($id): bool
    {
        if ($this->failDeletes || !array_key_exists($id, $this->values)) {
            return false;
        }

        unset($this->values[$id]);

        return true;
    }

    public function getStats(): ?array
    {
        return null;
    }

    public function flushAll(): bool
    {
        $this->values = [];

        return true;
    }
}

trait CreatePoolTrait
{
    private ?InMemoryDoctrineCache $doctrineCache = null;

    public function createCachePool(): CacheItemPoolInterface
    {
        return new DoctrineCachePool($this->getDoctrineCache());
    }

    private function getDoctrineCache(): InMemoryDoctrineCache
    {
        if (null === $this->doctrineCache) {
            $this->doctrineCache = new InMemoryDoctrineCache();
        }

        return $this->doctrineCache;
    }

    public function createSimpleCache(): CacheInterface
    {
        return $this->createCachePool();
    }
}
