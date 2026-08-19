<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Memcached\Tests;

use Cache\Adapter\Common\Exception\CachePoolException;
use Cache\Adapter\Memcached\MemcachedCachePool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class MemcachedCachePoolTest extends TestCase
{
    use CreatePoolTrait;

    #[RunInSeparateProcess]
    public function testValidTaggedBackendPayloadIsHit()
    {
        if (class_exists(\Memcached::class)) {
            $this->markTestSkipped('This test uses a local Memcached stub.');
        }

        eval('namespace { class Memcached { public const OPT_BINARY_PROTOCOL = 18; public mixed $payload; public function setOption(int $option, mixed $value): bool { return true; } public function get(string $key): mixed { return $this->payload; } } }');

        $client = new \Memcached();
        $client->payload = serialize([true, 'value', ['tag' => 'tag'], null]);
        $item = (new MemcachedCachePool($client))->getItem('key');

        self::assertTrue($item->isHit());
        self::assertSame(['tag' => 'tag'], $item->getPreviousTags());
    }

    /**
     * Ensures that items with a TTL larger than 30 days can be stored in memcached
     * https://github.com/memcached/memcached/wiki/Programming#expiration.
     */
    public function testTimeToLiveMoreThan30days()
    {
        $pool = $this->createCachePool();

        $item = $pool->getItem('365days');
        $item->set('4711');
        $item->expiresAfter(86400 * 365);
        $pool->save($item);

        $this->assertTrue($pool->getItem('365days')->isHit(), 'Item is not stored correctly');
    }

    #[RunInSeparateProcess]
    public function testGetMultipleUsesNativeGetMulti()
    {
        if (class_exists(\Memcached::class)) {
            $this->markTestSkipped('This test uses a local Memcached stub.');
        }

        eval('namespace { class Memcached { public const OPT_BINARY_PROTOCOL = 18; public const GET_PRESERVE_ORDER = 1; public array $values = []; public array $getCalls = []; public array $getMultiCalls = []; public function setOption(int $option, mixed $value): bool { return true; } public function get(string $key): mixed { $this->getCalls[] = $key; return $this->values[$key] ?? false; } public function getMulti(array $keys, int $flags = 0): array|false { $this->getMultiCalls[] = [$keys, $flags]; return array_map(fn (string $key): mixed => $this->values[$key] ?? null, array_combine($keys, $keys)); } } }');

        $client = new \Memcached();
        $client->values['first'] = serialize([true, 'one', [], null]);
        $pool = new MemcachedCachePool($client);

        self::assertSame(
            ['first' => 'one', 'missing' => 'default'],
            iterator_to_array($pool->getMultiple(['first', 'missing'], 'default'))
        );
        self::assertSame([[['first', 'missing'], \Memcached::GET_PRESERVE_ORDER]], $client->getMultiCalls);
        self::assertSame([], $client->getCalls);
    }

    #[RunInSeparateProcess]
    public function testGetMultipleReportsNativeFailure()
    {
        if (class_exists(\Memcached::class)) {
            $this->markTestSkipped('This test uses a local Memcached stub.');
        }

        eval('namespace { class Memcached { public const OPT_BINARY_PROTOCOL = 18; public const GET_PRESERVE_ORDER = 1; public function setOption(int $option, mixed $value): bool { return true; } public function getMulti(array $keys, int $flags = 0): false { return false; } } }');

        $this->expectException(CachePoolException::class);

        (new MemcachedCachePool(new \Memcached()))->getMultiple(['key']);
    }

    #[RunInSeparateProcess]
    public function testSetMultipleUsesNativeSetMultiAndRemovesOldTags()
    {
        if (class_exists(\Memcached::class)) {
            $this->markTestSkipped('This test uses a local Memcached stub.');
        }

        eval('namespace { class Memcached { public const OPT_BINARY_PROTOCOL = 18; public const GET_PRESERVE_ORDER = 1; public array $values = []; public array $setCalls = []; public array $setMultiCalls = []; public function setOption(int $option, mixed $value): bool { return true; } public function get(string $key): mixed { return $this->values[$key] ?? false; } public function getMulti(array $keys, int $flags = 0): array|false { return array_map(fn (string $key): mixed => $this->values[$key] ?? null, array_combine($keys, $keys)); } public function set(string $key, mixed $value, int $expiration = 0): bool { $this->setCalls[] = [$key, $value, $expiration]; $this->values[$key] = $value; return true; } public function setMulti(array $items, int $expiration = 0): bool { $this->setMultiCalls[] = [$items, $expiration]; foreach ($items as $key => $value) { $this->values[$key] = $value; } return true; } } }');

        $client = new \Memcached();
        $client->values['first'] = serialize([true, 'old', ['old' => 'old'], null]);
        $client->values['tag!old'] = ['first'];
        $pool = new MemcachedCachePool($client);

        self::assertTrue($pool->setMultiple(['first' => 'new', 'second' => false], 60));
        self::assertCount(1, $client->setMultiCalls);

        [$stored, $expiration] = $client->setMultiCalls[0];
        self::assertSame(['first', 'second'], array_keys($stored));
        self::assertSame('new', unserialize($stored['first'])[1]);
        self::assertFalse(unserialize($stored['second'])[1]);
        self::assertGreaterThan(0, $expiration);
        self::assertSame([], $client->values['tag!old']);
    }

    #[RunInSeparateProcess]
    public function testSetMultipleReportsATagIndexWriteFailure()
    {
        if (class_exists(\Memcached::class)) {
            $this->markTestSkipped('This test uses a local Memcached stub.');
        }

        eval('namespace { class Memcached { public const OPT_BINARY_PROTOCOL = 18; public const GET_PRESERVE_ORDER = 1; public array $values = []; public function setOption(int $option, mixed $value): bool { return true; } public function get(string $key): mixed { return $this->values[$key] ?? false; } public function getMulti(array $keys, int $flags = 0): array { return array_map(fn (string $key): mixed => $this->values[$key] ?? null, array_combine($keys, $keys)); } public function set(string $key, mixed $value, int $expiration = 0): false { return false; } public function setMulti(array $items, int $expiration = 0): bool { foreach ($items as $key => $value) { $this->values[$key] = $value; } return true; } } }');

        $client = new \Memcached();
        $client->values['key'] = serialize([true, 'old', ['tag' => 'tag'], null]);
        $client->values['tag!tag'] = ['key'];
        $pool = new MemcachedCachePool($client);

        self::assertFalse($pool->setMultiple(['key' => 'new']));
    }

    #[RunInSeparateProcess]
    public function testDeleteMultipleUsesNativeDeleteMultiAndRemovesOldTags()
    {
        if (class_exists(\Memcached::class)) {
            $this->markTestSkipped('This test uses a local Memcached stub.');
        }

        eval('namespace { class Memcached { public const OPT_BINARY_PROTOCOL = 18; public const GET_PRESERVE_ORDER = 1; public const RES_NOTFOUND = 16; public array $values = []; public array $deleteMultiCalls = []; private int $resultCode = 0; public function setOption(int $option, mixed $value): bool { return true; } public function get(string $key): mixed { return $this->values[$key] ?? false; } public function getMulti(array $keys, int $flags = 0): array|false { return array_map(fn (string $key): mixed => $this->values[$key] ?? null, array_combine($keys, $keys)); } public function set(string $key, mixed $value, int $expiration = 0): bool { $this->values[$key] = $value; return true; } public function delete(string $key): bool { if (array_key_exists($key, $this->values)) { unset($this->values[$key]); $this->resultCode = 0; return true; } $this->resultCode = self::RES_NOTFOUND; return false; } public function deleteMulti(array $keys, int $time = 0): array { $this->deleteMultiCalls[] = $keys; $deleted = []; foreach ($keys as $key) { if (array_key_exists($key, $this->values)) { unset($this->values[$key]); $deleted[$key] = true; } else { $deleted[$key] = self::RES_NOTFOUND; } } return $deleted; } public function getResultCode(): int { return $this->resultCode; } } }');

        $client = new \Memcached();
        $client->values['first'] = serialize([true, 'one', ['old' => 'old'], null]);
        $client->values['tag!old'] = ['first'];
        $pool = new MemcachedCachePool($client);

        self::assertTrue($pool->deleteMultiple(['first', 'missing']));
        self::assertSame([['first', 'missing']], $client->deleteMultiCalls);
        self::assertArrayNotHasKey('first', $client->values);
        self::assertSame([], $client->values['tag!old']);
    }

    #[RunInSeparateProcess]
    public function testDeleteMultipleReportsATagIndexWriteFailure()
    {
        if (class_exists(\Memcached::class)) {
            $this->markTestSkipped('This test uses a local Memcached stub.');
        }

        eval('namespace { class Memcached { public const OPT_BINARY_PROTOCOL = 18; public const GET_PRESERVE_ORDER = 1; public const RES_NOTFOUND = 16; public array $values = []; public function setOption(int $option, mixed $value): bool { return true; } public function get(string $key): mixed { return $this->values[$key] ?? false; } public function getMulti(array $keys, int $flags = 0): array { return array_map(fn (string $key): mixed => $this->values[$key] ?? null, array_combine($keys, $keys)); } public function set(string $key, mixed $value, int $expiration = 0): false { return false; } public function deleteMulti(array $keys, int $time = 0): array { foreach ($keys as $key) { unset($this->values[$key]); } return array_fill_keys($keys, true); } } }');

        $client = new \Memcached();
        $client->values['key'] = serialize([true, 'value', ['tag' => 'tag'], null]);
        $client->values['tag!tag'] = ['key'];
        $pool = new MemcachedCachePool($client);

        self::assertFalse($pool->deleteMultiple(['key']));
    }

    #[RunInSeparateProcess]
    public function testHierarchyDeleteReportsANativeIncrementFailure()
    {
        if (class_exists(\Memcached::class)) {
            $this->markTestSkipped('This test uses a local Memcached stub.');
        }

        eval('namespace { class Memcached { public const OPT_BINARY_PROTOCOL = 18; public const RES_NOTFOUND = 16; public function setOption(int $option, mixed $value): bool { return true; } public function get(string $key): false { return false; } public function increment(string $key, int $offset = 1, int $initialValue = 0): false { return false; } public function delete(string $key): bool { return true; } public function getResultCode(): int { return 0; } } }');

        self::assertFalse((new MemcachedCachePool(new \Memcached()))->deleteItem('|parent'));
    }

    #[RunInSeparateProcess]
    public function testDeleteMultipleReportsANativeIncrementFailure()
    {
        if (class_exists(\Memcached::class)) {
            $this->markTestSkipped('This test uses a local Memcached stub.');
        }

        eval('namespace { class Memcached { public const OPT_BINARY_PROTOCOL = 18; public const GET_PRESERVE_ORDER = 1; public const RES_NOTFOUND = 16; public function setOption(int $option, mixed $value): bool { return true; } public function get(string $key): false { return false; } public function getMulti(array $keys, int $flags = 0): array { return array_fill_keys($keys, null); } public function increment(string $key, int $offset = 1, int $initialValue = 0): false { return false; } public function deleteMulti(array $keys, int $time = 0): array { return array_fill_keys($keys, true); } } }');

        self::assertFalse((new MemcachedCachePool(new \Memcached()))->deleteMultiple(['|parent']));
    }

    #[DataProvider('invalidPayloadProvider')]
    #[RunInSeparateProcess]
    public function testInvalidBackendPayloadIsCacheMiss(mixed $payload)
    {
        if (class_exists(\Memcached::class)) {
            $this->markTestSkipped('This test uses a local Memcached stub.');
        }

        eval('namespace { class Memcached { public const OPT_BINARY_PROTOCOL = 18; public mixed $payload; public function setOption(int $option, mixed $value): bool { return true; } public function get(string $key): mixed { return $this->payload; } } }');

        $client = new \Memcached();
        $client->payload = $payload;
        $item = (new MemcachedCachePool($client))->getItem('key');

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
        yield 'incomplete class' => [str_replace('stdClass', 'GoneType', serialize([true, new \stdClass(), [], null]))];
    }
}
