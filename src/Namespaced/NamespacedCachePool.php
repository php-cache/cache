<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Namespaced;

use Cache\Hierarchy\HierarchicalPoolInterface;
use Psr\Cache\CacheItemInterface;

/**
 * Prefix all the stored items with a namespace. Also make sure you can clear all items
 * in that namespace.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class NamespacedCachePool implements HierarchicalPoolInterface
{
    /**
     * @type HierarchicalPoolInterface
     */
    private $cachePool;

    /**
     * @type string
     */
    private $namespace;

    /**
     * @type string
     */
    private $prefix;

    /**
     * @param HierarchicalPoolInterface $cachePool
     * @param string                    $namespace
     */
    public function __construct(HierarchicalPoolInterface $cachePool, $namespace)
    {
        $this->cachePool = $cachePool;
        $this->namespace = $namespace;
        $this->prefix    = HierarchicalPoolInterface::HIERARCHY_SEPARATOR.$this->namespace.HierarchicalPoolInterface::HIERARCHY_SEPARATOR;
    }

    /**
     * Add namespace prefix on the key.
     *
     * @param string $key
     */
    private function prefixValue(&$key)
    {
        // |namespace|key
        $key = $this->prefix.$key;
    }

    /**
     * @param array $keys
     */
    private function prefixValues(array &$keys)
    {
        foreach ($keys as &$key) {
            $this->prefixValue($key);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $this->prefixValue($key);

        return $this->cachePool->getItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = [])
    {
        $this->prefixValues($keys);

        return iterator_to_array($this->yieldWithoutPrefix($this->cachePool->getItems($keys)));
    }

    /**
     * @param CacheItemInterface[] $items
     *
     * @return \Generator|null
     */
    protected function yieldWithoutPrefix($items)
    {
        $prefixLength = strlen($this->prefix);
        foreach ($items as $key => $val) {
            if (strpos($key, $this->prefix) === 0) {
                $key = substr($key, $prefixLength);
            }
            yield str_replace($this->prefix, '', $key) => $val;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        $this->prefixValue($key);

        return $this->cachePool->hasItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return $this->cachePool->deleteItem(HierarchicalPoolInterface::HIERARCHY_SEPARATOR.$this->namespace);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        $this->prefixValue($key);

        return $this->cachePool->deleteItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $this->prefixValues($keys);

        return $this->cachePool->deleteItems($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        return $this->cachePool->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        return $this->cachePool->saveDeferred($item);
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        return $this->cachePool->commit();
    }
}
