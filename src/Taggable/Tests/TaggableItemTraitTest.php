<?php

/*
 * This file is part of php-cache\taggable-cache package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Taggable\Tests;

use Cache\Taggable\Tests\Helper\CacheItem;

class TaggableItemTraitTest extends \PHPUnit_Framework_TestCase
{
    public function testGetKey()
    {
        $item = new CacheItem('key');
        $this->assertEquals('key', $item->getKey());

        $item = new CacheItem('key!foo');
        $this->assertEquals('key', $item->getKey());

        $item = new CacheItem('key!foo!bar');
        $this->assertEquals('key', $item->getKey());
    }

    public function testGetTaggedKey()
    {
        $item = new CacheItem('key');
        $this->assertEquals('key', $item->getTaggedKey());

        $item = new CacheItem('key!foo');
        $this->assertEquals('key!foo', $item->getTaggedKey());

        $item = new CacheItem('key!foo!bar');
        $this->assertEquals('key!foo!bar', $item->getTaggedKey());
    }
}
