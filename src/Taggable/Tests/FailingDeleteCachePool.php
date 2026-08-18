<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Taggable\Tests;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class FailingDeleteCachePool implements CacheItemPoolInterface
{
    public bool $failClear = false;

    public bool $failDeleteItem = false;

    public bool $failDeleteItems = false;

    public bool $failSave = false;

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
        return !$this->failClear && $this->pool->clear();
    }

    public function deleteItem(string $key): bool
    {
        return !$this->failDeleteItem && $this->pool->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return !$this->failDeleteItems && $this->pool->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        return !$this->failSave && $this->pool->save($item);
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
