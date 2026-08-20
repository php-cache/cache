<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Memcache\Tests;

use Cache\Adapter\Memcache\MemcacheCachePool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class MemcacheCachePoolTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testTimeToLiveMoreThanThirtyDaysUsesAbsoluteTimestamp()
    {
        if (class_exists(\Memcache::class)) {
            $this->markTestSkipped('This test uses a local Memcache stub.');
        }

        eval('namespace { class Memcache { public ?int $ttl = null; public function get(string $key): mixed { return false; } public function set(string $key, mixed $value, int $flag, int $ttl): bool { $this->ttl = $ttl; return true; } } }');

        $client = new \Memcache();
        $pool = new MemcacheCachePool($client);
        $item = $pool->getItem('365days')->set('value')->expiresAfter(86400 * 365);

        self::assertTrue($pool->save($item));
        self::assertSame($item->getExpirationTimestamp(), $client->ttl);
    }

    #[RunInSeparateProcess]
    public function testValidTaggedBackendPayloadIsHit()
    {
        if (class_exists(\Memcache::class)) {
            $this->markTestSkipped('This test uses a local Memcache stub.');
        }

        eval('namespace { class Memcache { public array $payloads = []; public function get(string $key): mixed { return $this->payloads[$key] ?? false; } } }');

        $client = new \Memcache();
        $client->payloads = [
            'key' => serialize([true, 'value', [['tag', 'version']], null]),
            'tagv!'.substr(hash('sha256', 'tag'), 0, 59) => serialize([true, 'version', [], null]),
        ];
        $item = (new MemcacheCachePool($client))->getItem('key');

        self::assertTrue($item->isHit());
        self::assertSame(['tag' => 'tag'], $item->getPreviousTags());
        self::assertSame([['tag', 'version']], $item->getTagVersions());
    }

    #[RunInSeparateProcess]
    public function testDeleteReportsANativeFailureUnlessTheKeyIsMissing()
    {
        if (class_exists(\Memcache::class)) {
            $this->markTestSkipped('This test uses a local Memcache stub.');
        }

        eval('namespace { class Memcache { public mixed $payload = "value"; public function delete(string $key): false { return false; } public function get(string $key): mixed { return $this->payload; } } }');

        $client = new \Memcache();
        $pool = new class($client) extends MemcacheCachePool {
            public function clearKey(string $key): bool
            {
                return $this->clearOneObjectFromCache($key);
            }
        };
        self::assertFalse($pool->clearKey('key'));

        $client->payload = false;
        self::assertTrue($pool->clearKey('missing'));
    }

    #[DataProvider('invalidPayloadProvider')]
    #[RunInSeparateProcess]
    public function testInvalidBackendPayloadIsCacheMiss(mixed $payload)
    {
        if (class_exists(\Memcache::class)) {
            $this->markTestSkipped('This test uses a local Memcache stub.');
        }

        eval('namespace { class Memcache { public mixed $payload; public function get(string $key): mixed { return $this->payload; } } }');

        $client = new \Memcache();
        $client->payload = $payload;
        $item = (new MemcacheCachePool($client))->getItem('key');

        self::assertFalse($item->isHit());
        self::assertNull($item->get());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'missing' => [false];
        yield 'null' => [null];
        yield 'non-string' => [123];
        yield 'malformed' => ['not serialized'];
        yield 'wrong shape' => [serialize(['value'])];
        yield 'throwing unserialize' => [serialize(new ThrowingSerializedValue())];
        yield 'incomplete class' => [str_replace('stdClass', 'GoneType', serialize([true, new \stdClass(), [], null]))];
    }
}

final class ThrowingSerializedValue
{
    public function __serialize(): array
    {
        return [];
    }

    public function __unserialize(array $data): void
    {
        throw new \RuntimeException();
    }
}
