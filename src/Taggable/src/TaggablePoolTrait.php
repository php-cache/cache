<?php

/*
 * This file is part of php-cache\taggable-cache package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Taggable;

use Psr\Cache\CacheItemInterface;

/**
 * Use this trait with a CacheItemPoolInterface to support tagging.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
trait TaggablePoolTrait
{
    /**
     * This is a private storage where we cache/save the tag names and ids.
     *
     * @type array tagName => tagId
     */
    private $tags;

    /**
     * From Psr\Cache\CacheItemPoolInterface.
     *
     * @param CacheItemInterface $item
     *
     * @return bool
     */
    abstract public function save(CacheItemInterface $item);

    /**
     * From Psr\Cache\CacheItemPoolInterface.
     * This function should run $this->generateCacheKey to get a key using the tags.
     *
     * @param string $key
     *
     * @return CacheItemInterface
     */
    abstract public function getItem($key);

    /**
     * Return an CacheItemInterface for a tag.
     * This function MUST NOT run $this->generateCacheKey.
     *
     * @param $key
     *
     * @return CacheItemInterface
     */
    abstract protected function getItemWithoutGenerateCacheKey($key);

    /**
     * Make sure we do not use any invalid characters in the tag name.
     * If the tag name is invalid, an InvalidArgumentException should be thrown.
     * The actual tag name will be "tag!$name".
     *
     * @param string $name
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    abstract protected function validateTagName($name);

    /**
     * Reset the tag and return the new tag identifier.
     *
     * This will not delete anything form cache, only generate a new reference. This is a memory leak.
     *
     * @param string $name
     *
     * @return string
     */
    protected function flushTag($name)
    {
        $this->validateTagName($name);
        $item = $this->getItemWithoutGenerateCacheKey($this->getTagKey($name));

        return $this->generateNewTagId($item);
    }

    /**
     * Generate a good cache key that is dependent of the tags. This key should be the key of the CacheItem.
     *
     * @param string $key
     * @param array  $tags
     *
     * @return string
     */
    protected function generateCacheKey($key, array $tags)
    {
        if (empty($tags)) {
            return $key;
        }

        // We sort the tags because the order should not matter
        sort($tags);

        $tagIds = [];
        foreach ($tags as $tag) {
            if (isset($this->tags[$tag])) {
                $tagIds[] = $this->tags[$tag];
            } else {
                $tagIds[] = $this->getTagId($tag);
            }
        }
        $tagsNamespace = sha1(implode('|', $tagIds));

        return $key.TaggablePoolInterface::TAG_SEPARATOR.$tagsNamespace;
    }

    /**
     * Get the unique tag identifier for a given tag.
     *
     * @param string $name
     *
     * @return string
     */
    private function getTagId($name)
    {
        $this->validateTagName($name);
        $item = $this->getItemWithoutGenerateCacheKey($this->getTagKey($name));

        if ($item->isHit()) {
            return $item->get();
        }

        return $this->generateNewTagId($item);
    }

    /**
     * Get the tag identifier key for a given tag.
     *
     * @param string $name
     *
     * @return string
     */
    private function getTagKey($name)
    {
        return $name.TaggablePoolInterface::TAG_SEPARATOR.'tag';
    }

    /**
     * A TagId is retrieved from cache using the TagKey.
     *
     * @param \Psr\Cache\CacheItemPoolInterface $storage
     * @param CacheItemInterface                $item
     *
     * @return string
     */
    private function generateNewTagId(CacheItemInterface $item)
    {
        $value = str_replace('.', '', uniqid('', true));
        $item->set($value);
        $item->expiresAfter(null);
        $this->save($item);

        // Save to temporary tag store
        $this->tags[$item->getKey()] = $value;

        return $value;
    }
}
