<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Taggable;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * Lets you add tags to your cache items. Prepend the PSR-6 function arguments with an array of tag names for
 * functions not requiring an CacheItemInterface.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @deprecated Use Cache\TagInterop\TaggableCacheItemPoolInterface instead
 */
interface TaggablePoolInterface extends CacheItemPoolInterface
{
    const TAG_SEPARATOR = '!';

    /**
     * @param string $key
     *
     * @throws InvalidArgumentException
     *
     * @return TaggableItemInterface
     */
    public function getItem($key);

    /**
     * Clear all items with a tag in $tags.
     *
     * @param array $tags
     *
     * @return bool
     */
    public function clearTags(array $tags);
}
