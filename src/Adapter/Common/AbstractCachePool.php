<?php

/*
 * This file is part of php-cache\adapter-common package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Common;

use Cache\Adapter\Common\Exception\InvalidArgumentException;
use Cache\Taggable\TaggableItemInterface;
use Cache\Taggable\TaggablePoolInterface;
use Cache\Taggable\TaggablePoolTrait;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
abstract class AbstractCachePool implements CacheItemPoolInterface, TaggablePoolInterface
{
    use TaggablePoolTrait;

    /**
     * @type CacheItemInterface[] deferred
     */
    protected $deferred = [];

    /**
     * Make sure to commit before we destruct.
     */
    public function __destruct()
    {
        $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key, array $tags = [])
    {
        $this->validateKey($key);
        $taggedKey = $this->generateCacheKey($key, $tags);

        return $this->getItemWithoutGenerateCacheKey($taggedKey);
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemWithoutGenerateCacheKey($key)
    {
        if (isset($this->deferred[$key])) {
            $item = $this->deferred[$key];

            return is_object($item) ? clone $item : $item;
        }

        $func = function () use ($key) {
            return $this->fetchObjectFromCache($key);
        };

        return new CacheItem($key, $func);
    }

    /**
     * Fetch an object from the cache implementation.
     *
     * @param string $key
     *
     * @return array with [isHit, value]
     */
    abstract protected function fetchObjectFromCache($key);

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = [], array $tags = [])
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key, $tags);
        }

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key, array $tags = [])
    {
        return $this->getItem($key, $tags)->isHit();
    }

    /**
     * {@inheritdoc}
     */
    public function clear(array $tags = [])
    {
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $this->flushTag($tag);
            }

            return true;
        }

        // Clear the deferred items
        $this->deferred = [];

        return $this->clearAllObjectsFromCache();
    }

    /**
     * Clear all objects from cache.
     *
     * @return bool false if error
     */
    abstract protected function clearAllObjectsFromCache();

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key, array $tags = [])
    {
        return $this->deleteItems([$key], $tags);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys, array $tags = [])
    {
        $deleted = true;
        foreach ($keys as $key) {
            $this->validateKey($key);
            $taggedKey = $this->generateCacheKey($key, $tags);

            // Delete form deferred
            unset($this->deferred[$taggedKey]);

            if (!$this->clearOneObjectFromCache($taggedKey)) {
                $deleted = false;
            }
        }

        return $deleted;
    }

    /**
     * Remove one object from cache.
     *
     * @param string $key
     *
     * @return bool
     */
    abstract protected function clearOneObjectFromCache($key);

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        if ($item instanceof TaggableItemInterface) {
            $key = $item->getTaggedKey();
        } else {
            $key = $item->getKey();
        }

        $timeToLive = null;
        if ($item instanceof HasExpirationDateInterface) {
            if (null !== $expirationDate = $item->getExpirationDate()) {
                $timeToLive = $expirationDate->getTimestamp() - time();
            }
        }

        return $this->storeItemInCache($key, $item, $timeToLive);
    }

    /**
     * @param string             $key
     * @param CacheItemInterface $item
     * @param int|null           $ttl  seconds from now
     *
     * @return bool true if saved
     */
    abstract protected function storeItemInCache($key, CacheItemInterface $item, $ttl);

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if ($item instanceof TaggableItemInterface) {
            $key = $item->getTaggedKey();
        } else {
            $key = $item->getKey();
        }

        $this->deferred[$key] = $item;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $saved = true;
        foreach ($this->deferred as $item) {
            if (!$this->save($item)) {
                $saved = false;
            }
        }
        $this->deferred = [];

        return $saved;
    }

    /**
     * @param string $key
     *
     * @throws InvalidArgumentException
     */
    protected function validateKey($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(sprintf(
                'Cache key must be string, "%s" given', gettype($key)
            ));
        }

        if (preg_match('|[\{\}\(\)/\\\@\:]|', $key)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid key: "%s". The key contains one or more characters reserved for future extension: {}()/\@:',
                $key
            ));
        }
    }

    /**
     * @param string $name
     *
     * @throws InvalidArgumentException
     */
    protected function validateTagName($name)
    {
        $this->validateKey($name);
    }
}
