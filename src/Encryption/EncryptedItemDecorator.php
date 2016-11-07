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

use Cache\Adapter\Common\HasExpirationDateInterface;
use Cache\Taggable\TaggableItemInterface;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Psr\Cache\CacheItemInterface;

/**
 * Encrypt and Decrypt all the stored items.
 *
 * @author Daniel Bannert <d.bannert@anolilab.de>
 */
class EncryptedItemDecorator implements CacheItemInterface, HasExpirationDateInterface, TaggableItemInterface
{
    /**
     * @type CacheItemInterface
     */
    private $cacheItem;

    /**
     * @type Key
     */
    private $key;

    /**
     * @param CacheItemInterface $cacheItem
     * @param Key                $key
     */
    public function __construct(CacheItemInterface $cacheItem, Key $key)
    {
        $this->cacheItem = $cacheItem;
        $this->key       = $key;
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

        $this->cacheItem->set(Crypto::encrypt($json, $this->key));

        return $this;
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
        $this->cacheItem->expiresAt($expiration);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAfter($time)
    {
        $this->cacheItem->expiresAfter($time);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTags()
    {
        return $this->cacheItem->getTags();
    }

    /**
     * {@inheritdoc}
     */
    public function addTag($tag)
    {
        $this->cacheItem->addTag($tag);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTags(array $tags)
    {
        $this->cacheItem->setTags($tags);

        return $this;
    }

    /**
     * Creating a copy of the orginal CacheItemInterface object.
     */
    public function __clone()
    {
        $this->cacheItem = clone $this->cacheItem;
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
