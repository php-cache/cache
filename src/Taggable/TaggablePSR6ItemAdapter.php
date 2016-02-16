<?php

namespace Cache\Taggable;

use Cache\Taggable\TaggableItemInterface;
use Cache\Taggable\TaggablePoolInterface;
use Cache\Taggable\TaggablePoolTrait;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
* 
*/
class TaggablePSR6ItemAdapter implements TaggableItemInterface
{
    private $cacheItem;
    private $tags = [];

    private function __construct(CacheItemInterface $cacheItem)
    {
        $this->cacheItem = $cacheItem;
        if ($this->cacheItem->isHit()) {
            $rawItem = $this->cacheItem->get();

            if (is_array($rawItem) && isset($rawItem['tags'])) {
                $this->tags = $rawItem['tags'];
            }
        }
    }

    public static function makeTaggable(CacheItemInterface $cacheItem) // @TODO naming?
    {
        return new self($cacheItem);
    }

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

        if (is_array($rawItem) && isset($rawItem['value'])) {
            return $rawItem['value'];
        }

        return null;
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
        $this->cacheItem->set([
            'value' => $value,
            'tags' => $this->tags,
        ]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * {@inheritdoc}
     */
    public function setTags(array $tags)
    {
        $this->tags = $tags;
        $this->updateTags();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addTag($tag)
    {
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
            'tags' => $this->tags,
        ]);
    }
}
