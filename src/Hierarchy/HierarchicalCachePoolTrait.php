<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Hierarchy;

use Cache\Adapter\Common\AbstractCachePool;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
trait HierarchicalCachePoolTrait
{
    /** @var array<string, string> */
    private array $keyCache = [];

    /**
     * Get a value from the storage.
     */
    abstract public function getDirectValue(string $name): mixed;

    /**
     * Get a key to use with the hierarchy. If the key does not start with HierarchicalPoolInterface::SEPARATOR
     * this will return an unalterered key. This function supports a tagged key. Ie "foo:bar".
     *
     * @param string $key      The original key
     * @param string &$pathKey A cache key for the path. If this key is changed everything beyond that path is changed.
     */
    protected function getHierarchyKey(string $key, ?string &$pathKey = null): string
    {
        if (!$this->isHierarchyKey($key)) {
            return $key;
        }

        $key = $this->explodeKey($key);

        $keyString = '';
        // The comments below is for a $key = ["foo!tagHash", "bar!tagHash"]
        foreach ($key as $name) {
            // 1) $keyString = "foo!tagHash"
            // 2) $keyString = "foo!tagHash![foo_index]!bar!tagHash"
            $keyString .= $name;
            $pathKey = sha1('path'.AbstractCachePool::SEPARATOR_TAG.$keyString);

            if (isset($this->keyCache[$pathKey])) {
                $index = $this->keyCache[$pathKey];
            } else {
                $index = $this->getDirectValue($pathKey);
                $index = is_string($index) || is_int($index) ? (string) $index : '';
                $this->keyCache[$pathKey] = $index;
            }

            // 1) $keyString = "foo!tagHash![foo_index]!"
            // 2) $keyString = "foo!tagHash![foo_index]!bar!tagHash![bar_index]!"
            $keyString .= AbstractCachePool::SEPARATOR_TAG.$index.AbstractCachePool::SEPARATOR_TAG;
        }

        // Assert: $pathKey = "path!foo!tagHash![foo_index]!bar!tagHash"
        // Assert: $keyString = "foo!tagHash![foo_index]!bar!tagHash![bar_index]!"

        // Make sure we do not get awfully long (>250 chars) keys
        return sha1($keyString);
    }

    /**
     * Clear the cache for the keys.
     */
    protected function clearHierarchyKeyCache(): void
    {
        $this->keyCache = [];
    }

    /**
     * A hierarchy key MUST begin with the separator.
     */
    private function isHierarchyKey(string $key): bool
    {
        return HierarchicalPoolInterface::HIERARCHY_SEPARATOR === substr($key, 0, 1);
    }

    /**
     * This will take a hierarchy key ("|foo|bar") with tags ("|foo|bar!tagHash") and return an array with
     * each level in the hierarchy appended with the tags. ["foo!tagHash", "bar!tagHash"].
     *
     * @return list<string>
     */
    private function explodeKey(string $string): array
    {
        list($key, $tag) = explode(AbstractCachePool::SEPARATOR_TAG, $string.AbstractCachePool::SEPARATOR_TAG);

        if (HierarchicalPoolInterface::HIERARCHY_SEPARATOR === $key) {
            $parts = ['root'];
        } else {
            $parts = explode(HierarchicalPoolInterface::HIERARCHY_SEPARATOR, $key);
            // remove first element since it is always empty and replace it with 'root'
            $parts[0] = 'root';
        }

        return array_map(function ($level) use ($tag) {
            return $level.AbstractCachePool::SEPARATOR_TAG.$tag;
        }, $parts);
    }
}
