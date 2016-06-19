<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Common;

use Cache\Taggable\TaggableItemInterface;
use Psr\Cache\CacheItemInterface;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CacheItem implements HasExpirationDateInterface, CacheItemInterface, TaggableItemInterface
{
    /**
     * @type array
     */
    private $tags = [];

    /**
     * @type \Closure
     */
    private $callable;

    /**
     * @type string
     */
    private $key;

    /**
     * @type mixed
     */
    private $value;

    /**
     * @type \DateTimeInterface|null
     */
    private $expirationDate = null;

    /**
     * @type bool
     */
    private $hasValue = false;

    /**
     * @param string        $key
     * @param \Closure|bool $callable or boolean hasValue
     */
    public function __construct($key, $callable = null, $value = null)
    {
        $this->key = $key;

        if ($callable === true) {
            $this->hasValue = true;
            $this->value    = $value;
        } elseif ($callable !== false) {
            // This must be a callable or null
            $this->callable = $callable;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * {@inheritdoc}
     */
    public function set($value)
    {
        $this->value    = $value;
        $this->hasValue = true;
        $this->callable = null;

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

        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function isHit()
    {
        $this->initialize();

        if (!$this->hasValue) {
            return false;
        }

        if ($this->expirationDate !== null) {
            return $this->expirationDate > new \DateTime();
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getExpirationDate()
    {
        return $this->expirationDate;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAt($expiration)
    {
        if ($expiration instanceof \DateTimeInterface) {
            $this->expirationDate = clone $expiration;
        } else {
            $this->expirationDate = $expiration;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAfter($time)
    {
        if ($time === null) {
            $this->expirationDate = null;
        }

        if ($time instanceof \DateInterval) {
            $this->expirationDate = new \DateTime();
            $this->expirationDate->add($time);
        }

        if (is_int($time)) {
            $this->expirationDate = \DateTime::createFromFormat('U', time() + $time);
        }

        return $this;
    }

    public function getTags()
    {
        $this->initialize();

        return $this->tags;
    }

    public function addTag($tag)
    {
        $this->initialize();

        $this->tags[] = $tag;

        return $this;
    }

    public function setTags(array $tags)
    {
        $this->initialize();

        $this->tags = $tags;

        return $this;
    }

    /**
     * If callable is not null, execute it an populate this object with values.
     */
    private function initialize()
    {
        if ($this->callable !== null) {
            $f              = $this->callable;
            $result         = $f();
            $this->hasValue = $result[0];
            $this->value    = $result[1];
            $this->tags     = isset($result[2]) ? $result[2] : [];

            $this->callable = null;
        }
    }
}
