<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Taggable;

use Psr\Cache\CacheItemInterface;

/**
 * @internal
 *
 * An adapter for non-taggable cache items, to be used with the cache pool
 * adapter.
 *
 * This adapter stores tags along with the cached value, by storing wrapping
 * the item in an array structure containing both.
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 */
class TaggablePSR6ItemAdapter implements TaggableItemInterface
{
    /**
     * @type bool
     */
    private $initialized = false;

    /**
     * @type CacheItemInterface
     */
    private $cacheItem;

    /**
     * @type array<string>
     */
    private $tags = [];

    /**
     * @param CacheItemInterface $cacheItem
     */
    private function __construct(CacheItemInterface $cacheItem)
    {
        $this->cacheItem = $cacheItem;
    }

    /**
     * @param CacheItemInterface $cacheItem
     *
     * @return TaggableItemInterface
     */
    public static function makeTaggable(CacheItemInterface $cacheItem)
    {
        return new self($cacheItem);
    }

    /**
     * @return CacheItemInterface
     */
    public function unwrap()
    {
        return $this->cacheItem;
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
    public function get()
    {
        $rawItem = $this->cacheItem->get();

        // If it is a cache item we created
        if ($this->isItemCreatedHere($rawItem)) {
            return $rawItem['value'];
        }

        // This is an item stored before we used this fake cache
        return $rawItem;
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
    public function set($value)
    {
        $this->initializeTags();

        $this->cacheItem->set([
            'value' => $value,
            'tags'  => $this->tags,
        ]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTags()
    {
        $this->initializeTags();

        return $this->tags;
    }

    /**
     * {@inheritdoc}
     */
    public function setTags(array $tags)
    {
        $this->initialized = true;
        $this->tags        = $tags;
        $this->updateTags();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addTag($tag)
    {
        $this->initializeTags();
        $this->tags[] = $tag;
        $this->updateTags();

        return $this;
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

    private function updateTags()
    {
        $this->cacheItem->set([
            'value' => $this->get(),
            'tags'  => $this->tags,
        ]);
    }

    private function initializeTags()
    {
        if (!$this->initialized) {
            if ($this->cacheItem->isHit()) {
                $rawItem = $this->cacheItem->get();

                if ($this->isItemCreatedHere($rawItem)) {
                    $this->tags = $rawItem['tags'];
                }
            }

            $this->initialized = true;
        }
    }

    /**
     * Verify that the raw data is a cache item created by this class.
     *
     * @param mixed $rawItem
     *
     * @return bool
     */
    private function isItemCreatedHere($rawItem)
    {
        return is_array($rawItem) && isset($rawItem['value']) && isset($rawItem['tags']) && count($rawItem) === 2;
    }
}
