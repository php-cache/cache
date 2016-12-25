<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2016 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */


namespace Cache\Taggable;

/**
 * Use this trait with a CacheItemPoolInterface to support tagging.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @deprecated will be removed in 1.0
 */
trait TaggablePoolTrait
{
    /**
     * @param array $tags
     *
     * @return bool
     * @deprecated Use invalidateTags
     */
    public function clearTags(array $tags)
    {
        return $this->invalidateTags($tags);
    }
}
