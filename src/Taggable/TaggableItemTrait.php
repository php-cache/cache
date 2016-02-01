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

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
trait TaggableItemTrait
{
    /**
     * @type string
     */
    protected $taggedKey;

    /**
     * A key that is dependent on the tags.
     *
     * @return string
     */
    public function getTaggedKey()
    {
        return $this->taggedKey;
    }

    /**
     * Return the cache key for this item. This is the generic cache key that the calling library sees.
     *
     * @return string
     */
    protected function getKeyFromTaggedKey($taggedKey)
    {
        if (false === $pos = strpos($taggedKey, TaggablePoolInterface::TAG_SEPARATOR)) {
            return $taggedKey;
        }

        return substr($taggedKey, 0, $pos);
    }
}
