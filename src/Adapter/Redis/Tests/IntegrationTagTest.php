<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Redis\Tests;

use Cache\Adapter\Redis\RedisCachePool;
use Cache\IntegrationTests\TaggableCachePoolTest;

class IntegrationTagTest extends TaggableCachePoolTest
{
    use CreateRedisPoolTrait;
    use TagExpirationTestTrait;

    public function testClientPrefixAndSerializerKeepTagMembersUsable()
    {
        $client = new \Redis();
        $client->connect('127.0.0.1', 6379);
        $client->select(1);
        $client->setOption(\Redis::OPT_PREFIX, 'issue-131:');
        $client->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
        $pool = new RedisCachePool($client);

        try {
            self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
            self::assertTrue($pool->hasItem('key'));
            self::assertTrue($pool->invalidateTag('tag'));
            self::assertFalse($pool->hasItem('key'));
        } finally {
            $pool->clear();
            $client->close();
        }
    }
}
