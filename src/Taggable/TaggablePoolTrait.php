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

use Psr\Cache\InvalidArgumentException;

/**
 * Use this trait with a CacheItemPoolInterface to support tagging.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
trait TaggablePoolTrait
{
    /**
     * @param string $key
     *
     * @return TaggableItemInterface
     */
    abstract protected function getItem($key);

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
     * @param array $keys
     *
     * @throws InvalidArgumentException
     *
     * @return bool
     */
    abstract public function deleteItems(array $keys);

    /**
     * @param TaggableItemInterface $item
     *
     * @return $this
     */
    protected function saveTags(TaggableItemInterface $item)
    {
        $tags = $item->getTags();
        foreach ($tags as $tag) {
            $this->appendListItem($this->getTagKey($tag), $item->getKey());
        }

        return $this;
    }

    /**
     * @param array $tags
     *
     * @return bool
     */
    public function clearTags(array $tags)
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

    /**
     * Removes the key form all tag lists.
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
     * @param $tag
     *
     * @return string
     */
    protected function getTagKey($tag)
    {
        return 'tag'.TaggablePoolInterface::TAG_SEPARATOR.$tag;
    }
}
