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

/**
 * Lets you add tags to your cache items. Prepend the PSR-6 function arguments with an array of tag names for
 * functions not requiring an CacheItemInterface.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface TaggablePoolInterface
{
    const TAG_SEPARATOR = '!';

    public function getItem($key, array $tags = []);

    public function getItems(array $keys = [], array $tags = []);

    public function hasItem($key, array $tags = []);

    public function clear(array $tags = []);

    public function deleteItem($key, array $tags = []);

    public function deleteItems(array $keys, array $tags = []);
}
