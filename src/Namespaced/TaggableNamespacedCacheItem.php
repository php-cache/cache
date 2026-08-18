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

final class TaggableNamespacedCacheItem extends NamespacedCacheItem implements TaggableCacheItemInterface
{
    public function __construct(
        string $key,
        private readonly TaggableCacheItemInterface $item,
        object $owner,
        private readonly NamespacedTagMapper $tagMapper,
    ) {
        parent::__construct($key, $item, $owner);
    }

    public function getPreviousTags(): array
    {
        return $this->tagMapper->unmapTags($this->item->getPreviousTags());
    }

    public function setTags(array $tags): static
    {
        $this->item->setTags($this->tagMapper->mapTags($tags));

        return $this;
    }
}
