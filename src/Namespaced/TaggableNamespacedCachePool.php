<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Namespaced;

use Cache\TagInterop\TaggableCacheItemInterface;
use Cache\TagInterop\TaggableCacheItemPoolInterface;

final class TaggableNamespacedCachePool extends NamespacedCachePool implements TaggableCacheItemPoolInterface
{
    private readonly TaggableCacheItemPoolInterface $taggablePool;

    public function __construct(TaggableCacheItemPoolInterface $taggablePool, string $namespace)
    {
        $this->taggablePool = $taggablePool instanceof self ? $taggablePool->taggablePool : $taggablePool;
        parent::__construct($taggablePool, $namespace);
    }

    public function getItem(string $key): TaggableCacheItemInterface
    {
        $item = parent::getItem($key);
        if (!$item instanceof TaggableCacheItemInterface) {
            throw new \UnexpectedValueException('A taggable cache pool returned a non-taggable item.');
        }

        return $item;
    }

    /**
     * @param array<array-key, string> $keys
     *
     * @return iterable<string, TaggableCacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        foreach (parent::getItems($keys) as $key => $item) {
            if (!$item instanceof TaggableCacheItemInterface) {
                throw new \UnexpectedValueException('A taggable cache pool returned a non-taggable item.');
            }

            yield $key => $item;
        }
    }

    public function invalidateTag(string $tag): bool
    {
        return $this->taggablePool->invalidateTag($this->mapTag($tag));
    }

    public function invalidateTags(array $tags): bool
    {
        return $this->taggablePool->invalidateTags($this->mapTags($tags));
    }
}
