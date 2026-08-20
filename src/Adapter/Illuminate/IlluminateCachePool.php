<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Illuminate;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Adapter\Common\PhpUnserializer;
use Cache\Hierarchy\HierarchicalCachePoolTrait;
use Cache\Hierarchy\HierarchicalPoolInterface;
use Illuminate\Contracts\Cache\Store;

/**
 * This is a bridge between PSR-6 and an Illuminate cache store.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
class IlluminateCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;

    protected Store $store;

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        $data = serialize([true, $item->get(), $item->getTagVersions(), $item->getExpirationTimestamp()]);

        $key = $this->getHierarchyKey($item->getKey());
        if (null === $ttl) {
            return false !== $this->store->forever($key, $data);
        }

        return false !== $this->store->put($key, $data, $ttl);
    }

    protected function fetchObjectFromCache(string $key): array
    {
        $payload = $this->store->get($this->getHierarchyKey($key));
        if (!\is_string($payload)) {
            return [false, null, [], null];
        }

        if (!PhpUnserializer::unserialize($payload, $record)) {
            return [false, null, [], null];
        }

        if (!\is_array($record) || !array_is_list($record) || 4 !== \count($record) || true !== $record[0]) {
            return [false, null, [], null];
        }

        $tags = $record[2];
        if (!\is_array($tags)) {
            return [false, null, [], null];
        }

        $decodedTags = [];
        foreach ($tags as $tagVersion) {
            if (!\is_array($tagVersion) || !array_is_list($tagVersion) || 2 !== \count($tagVersion)) {
                return [false, null, [], null];
            }
            [$tag, $version] = $tagVersion;
            if (!\is_string($tag) || !\is_string($version)) {
                return [false, null, [], null];
            }

            $decodedTags[] = [$tag, $version];
        }

        $expiration = $record[3];
        if (!\is_int($expiration) && null !== $expiration) {
            return [false, null, [], null];
        }

        return [true, $record[1], $decodedTags, $expiration];
    }

    protected function clearAllObjectsFromCache(): bool
    {
        return $this->store->flush();
    }

    protected function clearOneObjectFromCache(string $key): bool
    {
        $path = null;
        $keyString = $this->getHierarchyKey($key, $path);
        $stored = $this->store->get($keyString);
        $generationAdvanced = true;
        if (null !== $path) {
            if (null === $this->store->get($path)) {
                $generationAdvanced = false !== $this->store->forever($path, 0);
            }
            $generationAdvanced = false !== $this->store->increment($path) && $generationAdvanced;
        }
        $this->clearHierarchyKeyCache();

        if (null === $stored) {
            return $generationAdvanced;
        }

        return $this->store->forget($keyString) && $generationAdvanced;
    }

    protected function getList(string $name): array
    {
        $storedList = $this->store->get($name);

        if (!\is_array($storedList)) {
            return [];
        }

        $list = [];
        foreach ($storedList as $item) {
            if (!\is_string($item)) {
                return [];
            }

            $list[] = $item;
        }

        return $list;
    }

    protected function removeList(string $name): bool
    {
        if (null === $this->store->get($name)) {
            return true;
        }

        return $this->store->forget($name);
    }

    protected function appendListItem(string $name, string $key): bool
    {
        $list = $this->getList($name);
        $list[] = $key;

        return false !== $this->store->forever($name, $list);
    }

    protected function removeListItem(string $name, string $key): bool
    {
        $list = $this->getList($name);

        foreach ($list as $i => $item) {
            if ($item === $key) {
                unset($list[$i]);
            }
        }

        return false !== $this->store->forever($name, $list);
    }

    public function getDirectValue(string $name): mixed
    {
        return $this->store->get($name);
    }
}
