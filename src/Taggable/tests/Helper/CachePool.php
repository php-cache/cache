<?php

/*
 * This file is part of php-cache\taggable-cache package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Taggable\Tests\Helper;

use Cache\Taggable\TaggablePoolTrait;
use Psr\Cache\CacheItemInterface;

/**
 * A cache pool used in tests.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CachePool
{
    use TaggablePoolTrait;

    /**
     * @type array
     */
    private $memoryCache;

    protected function validateTagName($name)
    {
    }

    public function getItem($key, array $tags = [])
    {
        $taggedKey = $this->generateCacheKey($key, $tags);

        return $this->getItemWithoutGenerateCacheKey($taggedKey);
    }

    protected function getItemWithoutGenerateCacheKey($key)
    {
        if (isset($this->memoryCache[$key])) {
            $item = $this->memoryCache[$key];
        } else {
            $item = new CacheItem($key);
        }

        return $item;
    }

    public function save(CacheItemInterface $item)
    {
        $this->memoryCache[$item->getTaggedKey()] = $item;

        return true;
    }

    public function exposeGenerateCacheKey($key, array $tags)
    {
        return $this->generateCacheKey($key, $tags);
    }

    public function exposeFlushTag($name)
    {
        return $this->flushTag($name);
    }
}
