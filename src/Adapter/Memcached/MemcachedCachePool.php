<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Memcached;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\Exception\InvalidArgumentException;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Adapter\Common\TagSupportWithArray;
use Cache\Hierarchy\HierarchicalCachePoolTrait;
use Cache\Hierarchy\HierarchicalPoolInterface;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class MemcachedCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;
    use TagSupportWithArray;

    /**
     * @type \Memcached
     */
    protected $cache;

    /**
     * @param \Memcached $cache
     */
    public function __construct(\Memcached $cache)
    {
        $this->cache = $cache;
        $this->cache->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
        $this->cache->setOption(\Memcached::OPT_SERIALIZER, \Memcached::SERIALIZER_PHP);
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        $value = $this->cache->get($this->getHierarchyKey($key));
        if (false === $result = (is_array($value) ? $value : unserialize($value))) {
            return [false, null, [], null];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        if (!is_array($keys)) {
            if (!$keys instanceof \Traversable) {
                throw new InvalidArgumentException('$keys is neither an array nor Traversable');
            }
            // Since we need to throw an exception if *any* key is invalid, it doesn't make sense to wrap iterators or something like that.
            $keys = iterator_to_array($keys, false);
        }
        $items = [];
        foreach ($keys as $key) {
            $this->validateKey($key);
            $items[] = $this->getHierarchyKey($key);
        }
        if (defined('\Memcached::GET_EXTENDED') === false) {
            $null    = null;
            $results = $this->cache->getMulti($items, $null, \Memcached::GET_PRESERVE_ORDER);
        } else {
            $results = $this->cache->getMulti($items, \Memcached::GET_PRESERVE_ORDER);
        }
        /**
         * @param $default
         * @param $items
         * @param $results
         * @param $keys
         *
         * @return \Generator
         */
        $return = function($default, $items, $results, $keys) {
            foreach ($keys as $idx => $key) {
                $value = (false === $return[$key] = (isset($results[$items[$idx]]) ? (is_array($results[$items[$idx]]) ? $results[$items[$idx]] : unserialize($results[$items[$idx]])) : false)) ? $default : $return[$key][1];
                yield $key => $value;
            }
        };
        return $return($default, $items, $results, $keys);
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!is_array($values)) {
            if (!$values instanceof \Traversable) {
                throw new InvalidArgumentException('$values is neither an array nor Traversable');
            }
        }
        $keys        = [];
        $arrayValues = [];
        foreach ($values as $key => $value) {
            if (is_int($key)) {
                $key = (string) $key;
            }
            $this->validateKey($key);
            $keys[]            = $key;
            $arrayValues[$key] = $value;
        }
        $items       = $this->getItems($keys);
        $itemSuccess = true;
        $set         = [];
        foreach ($items as $key => $item) {
            $item->expiresAfter($ttl);
            $set[$this->getHierarchyKey($key)] = [true, $arrayValues[$key], $item->getTags(), $item->getExpirationTimestamp()];
        }
        if ($ttl instanceof \DateInterval) {
            $ttl = $ttl->format('%s');
        } elseif ($ttl instanceof \DateTimeInterface) {
            $ttl = $ttl->getTimestamp() - time();
        }
        if (is_numeric($ttl)) {
            $ttl = intval($ttl);
            if ($ttl <= (86400 * 30)) {
                $ttl++;
            }
        }
        $itemSuccess = $this->cache->setMulti($set, $ttl);

        return $itemSuccess;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        if (!method_exists('\Memcached', 'deleteMulti')) {
            return parent::deleteMultiple($keys);
        }
        if (!is_array($keys)) {
            if (!$keys instanceof \Traversable) {
                throw new InvalidArgumentException('$keys is neither an array nor Traversable');
            }
            // Since we need to throw an exception if *any* key is invalid, it doesn't make sense to wrap iterators or something like that.
            $keys = iterator_to_array($keys, false);
        }
        $items = [];
        foreach ($keys as $key) {
            $this->validateKey($key);
            $items[] = $this->getHierarchyKey($key);
        }
        $this->cache->deleteMulti($items);

        return true;
        //return $this->deleteItems($keys);
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllObjectsFromCache()
    {
        return $this->cache->flush();
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key)
    {
        $this->commit();
        $path = null;
        $key  = $this->getHierarchyKey($key, $path);
        if ($path) {
            $this->cache->increment($path, 1, 0);
        }
        $this->clearHierarchyKeyCache();

        if ($this->cache->delete($key)) {
            return true;
        }

        // Return true if key not found
        return $this->cache->getResultCode() === \Memcached::RES_NOTFOUND;
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache(PhpCacheItem $item, $ttl)
    {
        if ($ttl === null) {
            $ttl = 0;
        } elseif ($ttl < 0) {
            return false;
        } elseif ($ttl > 86400 * 30) {
            // Any time higher than 30 days is interpreted as a unix timestamp date.
            // https://github.com/memcached/memcached/wiki/Programming#expiration
            $ttl = time() + $ttl;
        }

        $key = $this->getHierarchyKey($item->getKey());

        return $this->cache->set($key, serialize([true, $item->get(), $item->getTags(), $item->getExpirationTimestamp()]), $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function getDirectValue($name)
    {
        return $this->cache->get($name);
    }

    /**
     * {@inheritdoc}
     */
    public function setDirectValue($name, $value)
    {
        $this->cache->set($name, $value);
    }
}
