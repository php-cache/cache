<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Void;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Hierarchy\HierarchicalPoolInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class VoidCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    protected function fetchObjectFromCache(string $key): array
    {
        return [false, null, [], null];
    }

    protected function clearAllObjectsFromCache(): bool
    {
        return true;
    }

    protected function clearOneObjectFromCache(string $key): bool
    {
        return true;
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        return true;
    }

    /** @param list<string> $tags */
    public function clearTags(array $tags): bool
    {
        return true;
    }

    /** @return list<string> */
    protected function getList(string $name): array
    {
        return [];
    }

    protected function removeList(string $name): bool
    {
        return true;
    }

    protected function appendListItem(string $name, string $key): bool
    {
        return true;
    }

    protected function removeListItem(string $name, string $key): bool
    {
        return true;
    }
}
