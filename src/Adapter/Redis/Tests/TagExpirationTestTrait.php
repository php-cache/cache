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

trait TagExpirationTestTrait
{
    public function testRepeatedTagSavesKeepOneIndexEntry()
    {
        self::assertTrue($this->cache->save($this->cache->getItem('key')->set('first')->setTags(['tag'])));
        self::assertTrue($this->cache->save($this->cache->getItem('key')->set('second')->setTags(['tag'])));
        self::assertSame(['key'], $this->getClient()->zRangeByScore('php-cache:tag:tag', 0, '+inf'));
        self::assertCount(1, $this->getClient()->zRangeByScore('php-cache:tag:tag', -1, -1));

        self::assertTrue($this->cache->invalidateTag('tag'));
        self::assertFalse($this->cache->hasItem('key'));
        self::assertSame([], $this->getClient()->zRange('php-cache:tag:tag', 0, -1));
    }

    public function testFiniteItemGivesItsTagIndexAFiniteLifetime()
    {
        $item = $this->cache->getItem('finite')->set('value')->setTags(['finite']);
        $item->expiresAfter(60);

        self::assertTrue($this->cache->save($item));

        $tagTtl = $this->getClient()->ttl('php-cache:tag:finite');
        self::assertGreaterThan(0, $tagTtl);
        self::assertLessThanOrEqual(60, $tagTtl);
    }

    public function testMixedFiniteLifetimesKeepTheLongestExpiryInEitherSaveOrder()
    {
        $longFirst = $this->cache->getItem('long-first')->set('value')->setTags(['short-last']);
        $longFirst->expiresAfter(120);
        $shortLast = $this->cache->getItem('short-last')->set('value')->setTags(['short-last']);
        $shortLast->expiresAfter(30);

        self::assertTrue($this->cache->save($longFirst));
        self::assertTrue($this->cache->save($shortLast));

        $shortFirst = $this->cache->getItem('short-first')->set('value')->setTags(['long-last']);
        $shortFirst->expiresAfter(30);
        $longLast = $this->cache->getItem('long-last')->set('value')->setTags(['long-last']);
        $longLast->expiresAfter(120);

        self::assertTrue($this->cache->save($shortFirst));
        self::assertTrue($this->cache->save($longLast));

        $shortLastTtl = $this->getClient()->ttl('php-cache:tag:short-last');
        self::assertGreaterThan(90, $shortLastTtl);
        self::assertLessThanOrEqual(120, $shortLastTtl);

        $longLastTtl = $this->getClient()->ttl('php-cache:tag:long-last');
        self::assertGreaterThan(90, $longLastTtl);
        self::assertLessThanOrEqual(120, $longLastTtl);
    }

    public function testNonExpiringMemberKeepsTheTagPersistentInEitherSaveOrder()
    {
        $finiteFirst = $this->cache->getItem('finite-first')->set('value')->setTags(['persistent-last']);
        $finiteFirst->expiresAfter(60);
        $persistentLast = $this->cache->getItem('persistent-last')->set('value')->setTags(['persistent-last']);

        self::assertTrue($this->cache->save($finiteFirst));
        self::assertTrue($this->cache->save($persistentLast));

        $persistentFirst = $this->cache->getItem('persistent-first')->set('value')->setTags(['finite-last']);
        $finiteLast = $this->cache->getItem('finite-last')->set('value')->setTags(['finite-last']);
        $finiteLast->expiresAfter(60);

        self::assertTrue($this->cache->save($persistentFirst));
        self::assertTrue($this->cache->save($finiteLast));

        self::assertSame(-1, $this->getClient()->ttl('php-cache:tag:persistent-last'));
        self::assertSame(-1, $this->getClient()->ttl('php-cache:tag:finite-last'));

        self::assertTrue($this->cache->invalidateTags(['persistent-last', 'finite-last']));
        self::assertFalse($this->cache->hasItem('finite-first'));
        self::assertFalse($this->cache->hasItem('persistent-last'));
        self::assertFalse($this->cache->hasItem('persistent-first'));
        self::assertFalse($this->cache->hasItem('finite-last'));
    }

    public function testRemovingOrRetaggingMembersRecomputesTheTagLifetime()
    {
        $finite = $this->cache->getItem('finite-after-persistent')->set('value')->setTags(['remove-persistent']);
        $finite->expiresAfter(120);
        $persistent = $this->cache->getItem('persistent-to-remove')->set('value')->setTags(['remove-persistent']);

        self::assertTrue($this->cache->save($finite));
        self::assertTrue($this->cache->save($persistent));
        self::assertSame(-1, $this->getClient()->ttl('php-cache:tag:remove-persistent'));
        self::assertTrue($this->cache->deleteItem('persistent-to-remove'));

        $finiteTtl = $this->getClient()->ttl('php-cache:tag:remove-persistent');
        self::assertGreaterThan(90, $finiteTtl);
        self::assertLessThanOrEqual(120, $finiteTtl);

        $short = $this->cache->getItem('short-after-retag')->set('value')->setTags(['retag-longest']);
        $short->expiresAfter(30);
        $long = $this->cache->getItem('long-to-retag')->set('value')->setTags(['retag-longest']);
        $long->expiresAfter(120);

        self::assertTrue($this->cache->save($short));
        self::assertTrue($this->cache->save($long));
        self::assertGreaterThan(90, $this->getClient()->ttl('php-cache:tag:retag-longest'));

        $long = $this->cache->getItem('long-to-retag');
        self::assertTrue($this->cache->save($long->setTags(['replacement'])));

        $tagTtl = $this->getClient()->ttl('php-cache:tag:retag-longest');
        self::assertGreaterThan(0, $tagTtl);
        self::assertLessThanOrEqual(30, $tagTtl);
    }

    public function testReadingATagPrunesExpiredMembers()
    {
        $pool = new InspectableRedisCachePool($this->getClient());
        $live = $pool->getItem('live')->set('value')->setTags(['read-prune']);
        $live->expiresAfter(60);
        self::assertTrue($pool->save($live));

        $this->getClient()->zAdd('php-cache:tag:read-prune', time() - 1, 'expired');
        self::assertSame(['expired', 'live'], $this->getClient()->zRangeByScore('php-cache:tag:read-prune', 0, '+inf'));

        self::assertSame(['live'], $pool->getTagMembers('php-cache:tag:read-prune'));
        self::assertSame(['live'], $this->getClient()->zRangeByScore('php-cache:tag:read-prune', 0, '+inf'));
    }

    public function testWritingAndRemovingMembersPrunesExpiredScores()
    {
        $persistent = $this->cache->getItem('persistent')->set('value')->setTags(['write-prune']);
        self::assertTrue($this->cache->save($persistent));

        $this->getClient()->zAdd('php-cache:tag:write-prune', time() - 1, 'expired-on-write');

        $finite = $this->cache->getItem('finite-to-remove')->set('value')->setTags(['write-prune']);
        $finite->expiresAfter(60);
        self::assertTrue($this->cache->save($finite));
        self::assertSame(['persistent', 'finite-to-remove'], $this->getClient()->zRangeByScore('php-cache:tag:write-prune', 0, '+inf'));

        $this->getClient()->zAdd('php-cache:tag:write-prune', time() - 1, 'expired-on-remove');

        self::assertTrue($this->cache->deleteItem('finite-to-remove'));
        self::assertSame(['persistent'], $this->getClient()->zRangeByScore('php-cache:tag:write-prune', 0, '+inf'));
    }

    public function testTagIndexesUseAReservedBackendNamespace()
    {
        $item = $this->cache->getItem('item')->set('value')->setTags(['namespace']);
        self::assertTrue($this->cache->save($item));

        self::assertSame(['item'], $this->getClient()->zRangeByScore('php-cache:tag:namespace', 0, '+inf'));
        self::assertSame([], $this->getClient()->zRange('tag!namespace', 0, -1));

        $this->expectException(\Psr\Cache\InvalidArgumentException::class);
        $this->cache->getItem('php-cache:tag:namespace');
    }
}
