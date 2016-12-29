<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2016 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Encryption;

use Cache\Adapter\Common\PhpCachePool;
use Cache\Adapter\Common\TaggableCacheItemInterface;
use Cache\Taggable\TaggablePSR6PoolAdapter;
use Defuse\Crypto\Key;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Wrapps a CacheItemInterface with EncryptedItemDecorator.
 *
 * @author Daniel Bannert <d.bannert@anolilab.de>
 */
class EncryptedCachePool implements TaggableCacheItemInterface
{
    /**
     * @type TaggableCacheItemInterface
     */
    private $cachePool;

    /**
     * @type Key
     */
    private $key;

    /**
     * @param CacheItemPoolInterface $cachePool
     * @param Key                    $key
     */
    public function __construct(CacheItemPoolInterface $cachePool, Key $key)
    {
        $this->cachePool = TaggablePSR6PoolAdapter::makeTaggable($cachePool);
        $this->key       = $key;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $item = $this->cachePool->getItem($key);

        if (!($item instanceof EncryptedItemDecorator)) {
            return new EncryptedItemDecorator($item, $this->key);
        }

        return $item;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = [])
    {
        return array_map(function (CacheItemInterface $inner) {
            if (!($inner instanceof EncryptedItemDecorator)) {
                return new EncryptedItemDecorator($inner, $this->key);
            }

            return $inner;
        }, $this->cachePool->getItems($keys));
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        return $this->cachePool->hasItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return $this->cachePool->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        return $this->cachePool->deleteItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        return $this->cachePool->deleteItems($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        if (!($item instanceof EncryptedItemDecorator)) {
            $item = new EncryptedItemDecorator($item, $this->key);
        }

        return $this->cachePool->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if (!($item instanceof EncryptedItemDecorator)) {
            $item = new EncryptedItemDecorator($item, $this->key);
        }

        return $this->cachePool->saveDeferred($item);
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        return $this->cachePool->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateTags(array $tags)
    {
        return $this->cachePool->invalidateTags($tags);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateTag($tag)
    {
        return $this->cachePool->invalidateTag($tag);
    }
}
