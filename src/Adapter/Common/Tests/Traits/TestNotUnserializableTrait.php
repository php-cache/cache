<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Common\Tests\Traits;

use Cache\Adapter\Common\Tests\Fixture\NotUnserializable;
use Psr\Cache\CacheItemPoolInterface;

trait TestNotUnserializableTrait
{
    public function testNotUnserializable()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
        }

        $cache = $this->createCachePool();

        $item = $cache->getItem('foo');
        $cache->save($item->set(new NotUnserializable()));

        $item = $cache->getItem('foo');
        $this->assertFalse($item->isHit());

        foreach ($cache->getItems(['foo']) as $item) {
        }

        $cache->save($item->set(new NotUnserializable()));

        foreach ($cache->getItems(['foo']) as $item) {
        }

        $this->assertFalse($item->isHit());
    }

    /**
     * @return CacheItemPoolInterface that is used in the tests
     */
    abstract public function createCachePool();
}
