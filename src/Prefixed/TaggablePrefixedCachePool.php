<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Prefixed;

use Cache\TagInterop\TaggableCacheItemInterface;
use Cache\TagInterop\TaggableCacheItemPoolInterface;

final class TaggablePrefixedCachePool extends PrefixedCachePool implements TaggableCacheItemPoolInterface
{
    public function __construct(private readonly TaggableCacheItemPoolInterface $taggablePool, string $prefix)
    {
        parent::__construct($taggablePool, $prefix);
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
        return $this->taggablePool->invalidateTag($tag);
    }

    public function invalidateTags(array $tags): bool
    {
        return $this->taggablePool->invalidateTags($tags);
    }
}
