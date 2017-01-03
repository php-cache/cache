<?php

namespace Cache\Bridge\Psr16;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;

class Psr16Bridge implements CacheInterface
{
    /**
     * @var CacheItemPoolInterface
     */
    protected $cacheItemPool;

    /**
     * Psr16Bridge constructor.
     */
    public function __construct(CacheItemPoolInterface $cacheItemPool)
    {
        $this->cacheItemPool = $cacheItemPool;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        try {
            $item = $this->cacheItemPool->getItem($key);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new Exception\InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        if (!$item->isHit()) {
            return $default;
        }

        return $item->get();
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        try {
            $item = $this->cacheItemPool->getItem($key);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new Exception\InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        $item->set($value);
        $item->expiresAfter($ttl);

        return $this->cacheItemPool->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        try {
            return $this->cacheItemPool->deleteItem($key);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new Exception\InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return $this->cacheItemPool->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        if (!is_array($keys)) {
            if (!$keys instanceof \Traversable) {
                throw new Exception\InvalidArgumentException("\$keys is neither an array nor Traversable");
            }

            $keys = iterator_to_array($keys);
        }

        try {
            $items = $this->cacheItemPool->getItems($keys);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new Exception\InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        foreach ($items as $key => $item) {
            /** @var $item CacheItemInterface */
            if (!$item->isHit()) {
                yield $key => $default;
            }

            yield $key => $item->get();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!is_array($values)) {
            if (!$values instanceof \Traversable) {
                throw new Exception\InvalidArgumentException("\$values is neither an array nor Traversable");
            }

            $values = iterator_to_array($values);
        }

        try {
            $items = $this->cacheItemPool->getItems(array_keys($values));
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new Exception\InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        $itemSuccess = true;

        foreach ($items as $key => $item) {
            /** @var $item CacheItemInterface */
            $item->set($values[$key]);
            $item->expiresAfter($ttl);

            $itemSuccess = $itemSuccess && $this->cacheItemPool->saveDeferred($item);
        }

        return $itemSuccess && $this->cacheItemPool->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        if (!is_array($keys)) {
            if (!$keys instanceof \Traversable) {
                throw new Exception\InvalidArgumentException("\$keys is neither an array nor Traversable");
            }

            $keys = iterator_to_array($keys);
        }

        try {
            return $this->cacheItemPool->deleteItems($keys);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new Exception\InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        try {
            $item = $this->cacheItemPool->getItem($key);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            throw new Exception\InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        return $item->isHit();
    }
}