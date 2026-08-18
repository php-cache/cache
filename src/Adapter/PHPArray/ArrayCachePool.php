<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\PHPArray;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Hierarchy\HierarchicalCachePoolTrait;
use Cache\Hierarchy\HierarchicalPoolInterface;

/**
 * Array cache pool. You could set a limit of how many items you want to be stored to avoid memory leaks.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ArrayCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;

    /** @var array<array-key, mixed> */
    private array $cache;

    /** @var array<int, list<string>> */
    private array $keyMap = [];

    private ?int $limit;

    private int $currentPosition = 0;

    /**
     * @param array<array-key, mixed> $cache
     */
    public function __construct(?int $limit = null, array &$cache = [])
    {
        $this->cache = &$cache;
        $this->limit = $limit;
    }

    /** @return PhpCacheItem|array{bool, mixed, array<string, string>, int|null} */
    protected function getItemWithoutGenerateCacheKey(string $key): PhpCacheItem|array
    {
        if (isset($this->deferred[$key])) {
            $item = clone $this->deferred[$key];
            $item->moveTagsToPrevious();

            return $item;
        }

        return $this->fetchObjectFromCache($key);
    }

    /** @return array{bool, mixed, array<string, string>, int|null} */
    protected function fetchObjectFromCache(string $key): array
    {
        $keys = $this->getHierarchyKey($key);

        if (!$this->cacheIsset($keys)) {
            return [false, null, [], null];
        }

        $element = $this->decodeCacheElement($this->cacheToolkit($keys));
        if (null === $element) {
            return [false, null, [], null];
        }

        [$data, $tags, $timestamp] = $element;

        if (\is_object($data)) {
            $data = clone $data;
        }

        return [true, $data, $tags, $timestamp];
    }

    protected function clearAllObjectsFromCache(): bool
    {
        $this->cache = [];
        $this->keyMap = [];
        $this->currentPosition = 0;
        $this->clearHierarchyKeyCache();

        return true;
    }

    protected function clearOneObjectFromCache(string $key): bool
    {
        $this->commit();
        $keys = $this->getHierarchyKey($key);

        $this->clearHierarchyKeyCache();
        $this->cacheToolkit($keys, null, true);
        foreach ($this->keyMap as $position => $trackedKeys) {
            if ($trackedKeys === $keys) {
                unset($this->keyMap[$position]);
            }
        }

        return true;
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        $keys = $this->getHierarchyKey($item->getKey());
        $value = $item->get();
        if (\is_object($value)) {
            $value = clone $value;
        }
        if (null !== $this->limit) {
            $tracked = false;
            foreach ($this->keyMap as $trackedKeys) {
                if ($trackedKeys === $keys) {
                    $tracked = true;
                    break;
                }
            }

            if (!$tracked) {
                if (isset($this->keyMap[$this->currentPosition])) {
                    $this->cacheToolkit($this->keyMap[$this->currentPosition], null, true);
                }

                $this->keyMap[$this->currentPosition] = $keys;
                $this->currentPosition = ($this->currentPosition + 1) % $this->limit;
            }
        }

        $this->cacheToolkit($keys, [$value, $item->getTags(), $item->getExpirationTimestamp()]);

        return true;
    }

    public function getDirectValue(string $key): mixed
    {
        return $this->cache[$key] ?? null;
    }

    /** @return list<string> */
    protected function getList(string $name): array
    {
        $data = $this->cache[$name] ?? [];
        if (!\is_array($data)) {
            return [];
        }

        $list = [];
        foreach ($data as $value) {
            if (!\is_string($value)) {
                return [];
            }

            $list[] = $value;
        }

        return $list;
    }

    protected function removeList(string $name): bool
    {
        unset($this->cache[$name]);

        return true;
    }

    protected function appendListItem(string $name, string $key): bool
    {
        $list = $this->getList($name);
        $list[] = $key;
        $this->cache[$name] = $list;

        return true;
    }

    protected function removeListItem(string $name, string $key): bool
    {
        $list = $this->getList($name);
        foreach ($list as $i => $item) {
            if ($item === $key) {
                unset($list[$i]);
            }
        }

        $this->cache[$name] = array_values($list);

        return true;
    }

    /**
     * Used to manipulate cached data by extracting, inserting or deleting value.
     *
     * @param list<string> $keys
     */
    private function cacheToolkit(array $keys, mixed $value = null, bool $unset = false): mixed
    {
        $element = &$this->cache;

        while ([] !== $keys) {
            $key = array_shift($keys);
            if (!\is_array($element)) {
                $element = [];
            }

            if (!$keys && null === $value && $unset) {
                unset($element[$key]);
                unset($element);
                $element = null;
            } else {
                $element = &$element[$key];
            }
        }

        if (!$unset && null !== $value) {
            $element = $value;
        }

        return $element;
    }

    /**
     * Checking if given keys exists and is valid.
     *
     * @param list<string> $keys
     */
    private function cacheIsset(array $keys): bool
    {
        $data = $this->cache;

        foreach ($keys as $key) {
            if (!\is_array($data) || !\array_key_exists($key, $data)) {
                return false;
            }

            $data = $data[$key];
        }

        return \is_array($data) && \array_key_exists(0, $data);
    }

    /**
     * Get a key to use with the hierarchy. If the key does not start with HierarchicalPoolInterface::SEPARATOR
     * this will return an unalterered key. This function supports a tagged key. Ie "foo:bar".
     * With this overwrite we'll return array as keys.
     *
     * @param string $key The original key
     *
     * @return list<string>
     */
    protected function getHierarchyKey(string $key, ?string &$pathKey = null): array
    {
        if (!$this->isHierarchyKey($key)) {
            return [$key];
        }

        return $this->explodeKey($key);
    }

    /** @return array{mixed, array<string, string>, int|null}|null */
    private function decodeCacheElement(mixed $element): ?array
    {
        if (!\is_array($element)
            || !\array_key_exists(0, $element)
            || !\array_key_exists(1, $element)
            || !\array_key_exists(2, $element)
            || !\is_array($element[1])
            || (!\is_int($element[2]) && null !== $element[2])
        ) {
            return null;
        }

        $tags = [];
        foreach ($element[1] as $tag) {
            if (!\is_string($tag)) {
                return null;
            }

            $tags[$tag] = $tag;
        }

        return [$element[0], $tags, $element[2]];
    }
}
