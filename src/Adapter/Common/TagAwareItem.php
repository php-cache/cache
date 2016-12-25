<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2016 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Common;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * An item that supports tags. This interface is a soon-to-be-PSR.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface TagAwareItem
{
    /**
     * Adds a tag to a cache item.
     *
     * @param string|string[] $tags A tag or array of tags
     *
     * @throws InvalidArgumentException When $tag is not valid.
     *
     * @return CacheItemInterface
     */
    public function tag($tags);
}
