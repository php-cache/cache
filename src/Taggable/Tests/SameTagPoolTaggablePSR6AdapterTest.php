<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Taggable\Tests;

use Cache\IntegrationTests\TaggableCachePoolTest;
use Cache\Taggable\TaggablePSR6PoolAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter as SymfonyArrayAdapter;

class SameTagPoolTaggablePSR6AdapterTest extends TaggableCachePoolTest
{
    public function createCachePool()
    {
        return TaggablePSR6PoolAdapter::makeTaggable(new SymfonyArrayAdapter());
    }

    /**
     * Test that keys are not added more than once.
     */
    public function testNoDuplicateKeyInTag()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
        }

        $pool = $this->createCachePool();

        $getTagKey = new \ReflectionMethod($pool, 'getTagKey');
        $getList   = new \ReflectionMethod($pool, 'getList');
        $getTagKey->setAccessible(true);
        $getList->setAccessible(true);

        $tagKey = $getTagKey->invoke($pool, 'tag');

        // Save the item once.
        $item = $pool->getItem('key');
        $item->set('value');
        $item->setTags(['tag']);
        $pool->save($item);

        $this->assertSame(['key'], $getList->invoke($pool, $tagKey));

        // Save the item again.
        $item2 = $pool->getItem('key');
        $item2->setTags(['tag']);
        $pool->save($item2);

        // But the key, must not be in the list twice.
        $this->assertSame(
            ['key'],
            $getList->invoke($pool, $tagKey),
            'The "key", should be present only once in the list'
        );
    }

    /**
     * Test that keys are removed from tag, when removed.
     */
    public function testRemoveKeyFromTag()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
        }

        $pool = $this->createCachePool();

        $getTagKey = new \ReflectionMethod($pool, 'getTagKey');
        $getList   = new \ReflectionMethod($pool, 'getList');
        $getTagKey->setAccessible(true);
        $getList->setAccessible(true);

        $bbTagKey = $getTagKey->invoke($pool, 'tag');

        // Create an item with a tag
        $item = $pool->getItem('key');
        $item->set('value');
        $item->setTags(['tag']);
        $pool->save($item);

        $this->assertSame(['key'], $getList->invoke($pool, $bbTagKey));

        // Remove the tag from the item.
        $item2 = $pool->getItem('key');
        $item2->setTags([]);
        $pool->save($item2);

        // The key must be removed from the list.
        $this->assertSame([], $getList->invoke($pool, $bbTagKey));
    }
}
