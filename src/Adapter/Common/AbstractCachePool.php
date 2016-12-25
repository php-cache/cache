<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2016 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Common;

use Cache\Adapter\Common\Exception\CacheException;
use Cache\Adapter\Common\Exception\CachePoolException;
use Cache\Adapter\Common\Exception\InvalidArgumentException;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
abstract class AbstractCachePool implements PhpCachePool, LoggerAwareInterface
{
    const SEPARATOR_TAG = '!';

    /**
     * @type LoggerInterface
     */
    private $logger;

    /**
     * @type CacheItemInterface[] deferred
     */
    protected $deferred = [];

    /**
     * @param PhpCacheItem $item
     * @param int|null     $ttl  seconds from now
     *
     * @return bool true if saved
     */
    abstract protected function storeItemInCache(PhpCacheItem $item, $ttl);

    /**
     * Fetch an object from the cache implementation.
     *
     * If it is a cache miss, it MUST return [false, null, [], null]
     *
     * @param string $key
     *
     * @return array with [isHit, value, tags[], expirationTimestamp]
     */
    abstract protected function fetchObjectFromCache($key);

    /**
     * Clear all objects from cache.
     *
     * @return bool false if error
     */
    abstract protected function clearAllObjectsFromCache();

    /**
     * Remove one object from cache.
     *
     * @param string $key
     *
     * @return bool
     */
    abstract protected function clearOneObjectFromCache($key);

    /**
     * Get an array with all the values in the list named $name.
     *
     * @param string $name
     *
     * @return array
     */
    abstract protected function getList($name);

    /**
     * Remove the list.
     *
     * @param string $name
     *
     * @return bool
     */
    abstract protected function removeList($name);

    /**
     * Add a item key on a list named $name.
     *
     * @param string $name
     * @param string $key
     */
    abstract protected function appendListItem($name, $key);

    /**
     * Remove an item from the list.
     *
     * @param string $name
     * @param string $key
     */
    abstract protected function removeListItem($name, $key);

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
    public function getItem($key)
    {
        $this->validateKey($key);
        if (isset($this->deferred[$key])) {
            $item = $this->deferred[$key];

            return is_object($item) ? clone $item : $item;
        }

        $func = function () use ($key) {
            try {
                return $this->fetchObjectFromCache($key);
            } catch (\Exception $e) {
                $this->handleException($e, __FUNCTION__);
            }
        };

        return new CacheItem($key, $func);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = [])
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        try {
            return $this->getItem($key)->isHit();
        } catch (\Exception $e) {
            $this->handleException($e, __FUNCTION__);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        // Clear the deferred items
        $this->deferred = [];

        try {
            return $this->clearAllObjectsFromCache();
        } catch (\Exception $e) {
            $this->handleException($e, __FUNCTION__);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        try {
            return $this->deleteItems([$key]);
        } catch (\Exception $e) {
            $this->handleException($e, __FUNCTION__);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $deleted = true;
        foreach ($keys as $key) {
            $this->validateKey($key);

            // Delete form deferred
            unset($this->deferred[$key]);

            // We have to commit here to be able to remove deferred hierarchy items
            $this->commit();
            $this->preRemoveItem($key);

            if (!$this->clearOneObjectFromCache($key)) {
                $deleted = false;
            }
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        if (!$item instanceof PhpCacheItem) {
            throw new InvalidArgumentException('Cache items are not transferable between pools. Item MUST implement PhpCacheItem.');
        }

        $this->saveTags($item);
        $timeToLive = null;
        if (null !== $timestamp = $item->getExpirationTimestamp()) {
            $timeToLive = $timestamp - time();

            if ($timeToLive < 0) {
                return $this->deleteItem($item->getKey());
            }
        }

        try {
            return $this->storeItemInCache($item, $timeToLive);
        } catch (\Exception $e) {
            $this->handleException($e, __FUNCTION__);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        $this->deferred[$item->getKey()] = $item;

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
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Logs with an arbitrary level if the logger exists.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     */
    protected function log($level, $message, array $context = [])
    {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Log exception and rethrow it.
     *
     * @param \Exception $e
     * @param string     $function
     *
     * @throws CachePoolException
     */
    private function handleException(\Exception $e, $function)
    {
        $level = 'alert';
        if ($e instanceof InvalidArgumentException) {
            $level = 'warning';
        }

        $this->log($level, $e->getMessage(), ['exception' => $e]);
        if (!$e instanceof CacheException) {
            $e = new CachePoolException(sprintf('Exception thrown when executing "%s". ', $function), 0, $e);
        }

        throw $e;
    }

    /**
     * @param array $tags
     *
     * @return bool
     */
    public function invalidateTags(array $tags)
    {
        $itemIds = [];
        foreach ($tags as $tag) {
            $itemIds = array_merge($itemIds, $this->getList($this->getTagKey($tag)));
        }

        // Remove all items with the tag
        $success = $this->deleteItems($itemIds);

        if ($success) {
            // Remove the tag list
            foreach ($tags as $tag) {
                $this->removeList($this->getTagKey($tag));
            }
        }

        return $success;
    }

    public function invalidateTag($tag)
    {
        return $this->invalidateTags([$tag]);
    }

    /**
     * @param PhpCacheItem $item
     */
    protected function saveTags(PhpCacheItem $item)
    {
        $tags = $item->getTags();
        foreach ($tags as $tag) {
            $this->appendListItem($this->getTagKey($tag), $item->getKey());
        }
    }

    /**
     * Removes the key form all tag lists. When an item with tags is removed
     * we MUST remove the tags. If we fail to remove the tags a new item with
     * the same key will automatically get the previous tags.
     *
     * @param string $key
     *
     * @return $this
     */
    protected function preRemoveItem($key)
    {
        $tags = $this->getItem($key)->getTags();
        foreach ($tags as $tag) {
            $this->removeListItem($this->getTagKey($tag), $key);
        }

        return $this;
    }

    /**
     * @param string $tag
     *
     * @return string
     */
    protected function getTagKey($tag)
    {
        return 'tag'.self::SEPARATOR_TAG.$tag;
    }
}
