<?php

namespace Cache\Adapter\Common;

use Psr\Cache\CacheItemInterface;
use \Psr\Cache\InvalidArgumentException;

/**
 * An item that supports tags. This interface is a soon-to-be-PSR.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface TaggableItem
{
    /**
     * Adds a tag to a cache item.
     *
     * @param string|string[] $tags A tag or array of tags
     *
     * @return CacheItemInterface
     *
     * @throws InvalidArgumentException When $tag is not valid.
     */
    public function tag($tags);
}
