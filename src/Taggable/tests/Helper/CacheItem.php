<?php

/*
 * This file is part of php-cache\taggable-cache package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Taggable\Tests\Helper;

use Cache\Taggable\TaggableItemInterface;
use Cache\Taggable\TaggableItemTrait;
use Psr\Cache\CacheItemInterface;

class CacheItem implements CacheItemInterface, TaggableItemInterface
{
    use TaggableItemTrait;

    /**
     * @type string
     */
    private $key;

    /**
     * @type string
     */
    private $value;

    /**
     * @type bool
     */
    private $hasValue = false;

    /**
     * @param string $key
     */
    public function __construct($key)
    {
        $this->taggedKey = $key;
        $this->key       = $this->getKeyFromTaggedKey($key);
    }

    /**
     * @return bool
     */
    public function isHit()
    {
        return $this->hasValue;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * @param string $value
     *
     * @return CacheItem
     */
    public function set($value)
    {
        $this->hasValue = true;
        $this->value    = $value;

        return $this;
    }

    public function expiresAt($expiration)
    {
        // TODO: Implement expiresAt() method.
    }

    public function expiresAfter($time)
    {
        // TODO: Implement expiresAfter() method.
    }
}
