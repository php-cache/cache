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
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface TaggableItemInterface extends CacheItemInterface
{
    /**
     * Get an array with the tags.
     *
     * @return array
     */
    public function getTags();

    /**
     * Replace the current tags with a new set of tags.
     *
     * @param array $tags
     *
     * @return $this
     */
    public function setTags(array $tags);

    /**
     * Append a tag.
     *
     * @param string $tag
     *
     * @return $this
     */
    public function addTag($tag);
}
