<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Encrypted;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Cache\Adapter\Common\HasExpirationDateInterface;
use Cache\Taggable\TaggableItemInterface;
use Psr\Cache\CacheItemInterface;

class ItemDecorator implements CacheItemInterface, HasExpirationDateInterface, TaggableItemInterface
{
    /**
     * @type CacheItemPoolInterface
     */
    private $cacheItem;

    /**
     * @type Key
     */
    private $key;

    /**
     * @param CacheItemPoolInterface $cacheItem
     * @param Key                    $key
     */
    public function __construct(CacheItemInterface $cacheItem, Key $key)
    {
        $this->cacheItem = $cacheItem;
        $this->key = $key;
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return $this->cacheItem->getKey();
    }

    /**
     * {@inheritdoc}
     */
    public function set($value)
    {
        $type = gettype($value);

        if ($type === 'object') {
            $value = serialize($value);
        }

        $json = json_encode(['type' => $type, 'value' => $value]);

        return $this->cacheItem->set(Crypto::encrypt($json, $this->key));
    }

    /**
     * {@inheritdoc}
     */
    public function get()
    {
        if (!$this->isHit()) {
            return;
        }

        $item = json_decode(Crypto::decrypt($this->cacheItem->get(), $this->key), true);

        return $this->transform($item);
    }

    /**
     * {@inheritdoc}
     */
    public function isHit()
    {
        return $this->cacheItem->isHit();
    }

    /**
     * {@inheritdoc}
     */
    public function getExpirationDate()
    {
        return $this->cacheItem->getExpirationDate();
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAt($expiration)
    {
        return $this->cacheItem->expiresAt($expiration);
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAfter($time)
    {
        return $this->cacheItem->expiresAfter($time);
    }

    public function getTags()
    {
        return $this->cacheItem->getTags();
    }

    public function addTag($tag)
    {
        return $this->cacheItem->addTag($tag);
    }

    public function setTags(array $tags)
    {
       return $this->cacheItem->setTags($tags);
    }

    /**
     * Transfrom value back to it orginal type.
     *
     * @param array $item
     *
     * @return mixed
     */
    private function transform(array $item)
    {
        if ($item['type'] === 'object') {
            return unserialize($item['value']);
        }

        $value = $item['value'];

        settype($value, $item['type']);

        return $value;
    }
}
