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

final class TaggablePrefixedCacheItem extends PrefixedCacheItem implements TaggableCacheItemInterface
{
    public function __construct(
        string $key,
        private readonly TaggableCacheItemInterface $item,
        object $owner,
    ) {
        parent::__construct($key, $item, $owner);
    }

    public function getPreviousTags(): array
    {
        return $this->item->getPreviousTags();
    }

    public function setTags(array $tags): static
    {
        $this->item->setTags($tags);

        return $this;
    }
}
