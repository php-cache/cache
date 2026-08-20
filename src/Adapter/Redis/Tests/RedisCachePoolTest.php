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

use Cache\Adapter\Common\Exception\CachePoolException;
use Cache\Adapter\Redis\RedisCachePool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class RedisCachePoolTest extends TestCase
{
    /**
     * Tests that an exception is thrown if invalid object is
     * passed to the constructor.
     */
    public function testConstructorWithInvalidObject()
    {
        $this->expectException(CachePoolException::class);

        new RedisCachePool(new \stdClass());
    }

    #[RunInSeparateProcess]
    public function testRedisArrayRoutesTagScriptsByTheTagKey()
    {
        if (class_exists(\Redis::class) || class_exists(\RedisArray::class)) {
            $this->markTestSkipped('This test uses local Redis and RedisArray stubs.');
        }

        eval(<<<'PHP'
            namespace {
                class Redis
                {
                    public array $evalCalls = [];
                    private ?string $tagVersion = null;

                    public function eval(string $script, array $arguments = [], int $keyCount = 0): int|string|false
                    {
                        $this->evalCalls[] = [$script, $arguments, $keyCount];

                        if (str_contains($script, "'@generation:' .. ARGV[1]")) {
                            $this->tagVersion = '@generation:'.$arguments[1];

                            return 1;
                        }
                        if (str_contains($script, "ZRANGEBYSCORE', KEYS[1], -1, -1")) {
                            return $this->tagVersion ?? false;
                        }

                        return 1;
                    }
                }

                class RedisArray
                {
                    public Redis $node;
                    public array $targetKeys = [];

                    public function __construct()
                    {
                        $this->node = new Redis();
                    }

                    public function _instance(string $host): Redis
                    {
                        return $this->node;
                    }

                    public function _target(string $key): string
                    {
                        $this->targetKeys[] = $key;

                        return 'redis:6379';
                    }

                    public function get(string $key): false
                    {
                        return false;
                    }

                    public function set(string $key, mixed $value): bool
                    {
                        return true;
                    }
                }
            }
            PHP);

        $client = new \RedisArray();
        $pool = new RedisCachePool($client);

        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
        self::assertSame(array_fill(0, 4, 'php-cache:tag:tag'), $client->targetKeys);
        self::assertCount(4, $client->node->evalCalls);

        $appendCall = $client->node->evalCalls[3];
        self::assertStringContainsString("redis.call('ZADD'", $appendCall[0]);
        self::assertStringContainsString("redis.call('EXPIRE', KEYS[1]", $appendCall[0]);
        self::assertStringNotContainsString("redis.call('EXPIREAT'", $appendCall[0]);
        self::assertSame(['php-cache:tag:tag', 'key', 0], \array_slice($appendCall[1], 0, 3));
        self::assertSame(1, $appendCall[2]);
    }

    #[RunInSeparateProcess]
    public function testValidTaggedBackendPayloadIsHit()
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public mixed $payload; public function get(string $key): mixed { return $this->payload; } public function eval(string $script, array $arguments = [], int $keyCount = 0): string { return "@generation:version"; } } }');

        $client = new \Redis();
        $client->payload = serialize([true, 'value', [['tag', 'version']], null]);
        $item = (new RedisCachePool($client))->getItem('key');

        self::assertTrue($item->isHit());
        self::assertSame(['tag' => 'tag'], $item->getPreviousTags());
        self::assertSame([['tag', 'version']], $item->getTagVersions());
    }

    #[RunInSeparateProcess]
    public function testDeleteReportsANativeFailure()
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public function get(string $key): false { return false; } public function del(string $key): false { return false; } } }');

        self::assertFalse((new RedisCachePool(new \Redis()))->deleteItem('key'));
    }

    #[RunInSeparateProcess]
    public function testTagInvalidationReportsANativeListDeleteFailure()
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { private int $deleteCalls = 0; public function eval(string $script, array $arguments = [], int $keyCount = 0): array { return []; } public function del(string $key): int|false { return 0 === $this->deleteCalls++ ? 1 : false; } } }');

        self::assertFalse((new RedisCachePool(new \Redis()))->invalidateTag('tag'));
    }

    #[RunInSeparateProcess]
    public function testTagInvalidationReportsANativeVersionDeleteFailure()
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { private int $deleteCalls = 0; public function eval(string $script, array $arguments = [], int $keyCount = 0): array { return []; } public function del(string $key): int|false { return 0 === $this->deleteCalls++ ? false : 1; } } }');

        self::assertFalse((new RedisCachePool(new \Redis()))->invalidateTag('tag'));
    }

    #[RunInSeparateProcess]
    public function testTagInvalidationReportsANativeListReadFailure()
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public function eval(string $script, array $arguments = [], int $keyCount = 0): false { return false; } public function del(string $key): int { return 0; } } }');

        self::assertFalse((new RedisCachePool(new \Redis()))->invalidateTag('tag'));
    }

    #[RunInSeparateProcess]
    public function testSaveReportsANativeTagIndexWriteFailure()
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { private ?string $tagVersion = null; public function get(string $key): false { return false; } public function set(string $key, mixed $value): bool { return true; } public function eval(string $script, array $arguments = [], int $keyCount = 0): int|string|false { if (str_contains($script, "\'@generation:\' .. ARGV[1]")) { $this->tagVersion = "@generation:".$arguments[1]; return 1; } if (str_contains($script, "ZRANGEBYSCORE\', KEYS[1], -1, -1")) { return $this->tagVersion ?? false; } return false; } } }');

        $pool = new RedisCachePool(new \Redis());

        self::assertFalse($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
    }

    #[RunInSeparateProcess]
    public function testDeleteReportsANativeTagIndexWriteFailure()
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public array $values = []; private ?string $tagVersion = null; public function get(string $key): mixed { return $this->values[$key] ?? false; } public function set(string $key, mixed $value): bool { $this->values[$key] = $value; return true; } public function eval(string $script, array $arguments = [], int $keyCount = 0): int|string|false { if (str_contains($script, "\'@generation:\' .. ARGV[1]")) { $this->tagVersion = "@generation:".$arguments[1]; return 1; } if (str_contains($script, "ZRANGEBYSCORE\', KEYS[1], -1, -1")) { return $this->tagVersion ?? false; } return str_contains($script, "\'ZREM\'") ? false : 1; } public function del(string $key): int { unset($this->values[$key]); return 1; } } }');

        $pool = new RedisCachePool(new \Redis());
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));

        self::assertFalse($pool->deleteItem('key'));
    }

    #[RunInSeparateProcess]
    public function testSaveReportsANativePreviousTagIndexRemovalFailure()
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public array $values = []; public bool $failTagRemoval = false; private ?string $tagVersion = null; public function get(string $key): mixed { return $this->values[$key] ?? false; } public function set(string $key, mixed $value): bool { $this->values[$key] = $value; return true; } public function eval(string $script, array $arguments = [], int $keyCount = 0): int|string|false { if (str_contains($script, "\'@generation:\' .. ARGV[1]")) { $this->tagVersion = "@generation:".$arguments[1]; return 1; } if (str_contains($script, "ZRANGEBYSCORE\', KEYS[1], -1, -1")) { return $this->tagVersion ?? false; } return $this->failTagRemoval && str_contains($script, "\'ZREM\'") ? false : 1; } } }');

        $client = new \Redis();
        $pool = new RedisCachePool($client);
        self::assertTrue($pool->save($pool->getItem('key')->set('original')->setTags(['tag'])));
        $client->failTagRemoval = true;

        self::assertFalse($pool->save($pool->getItem('key')->set('replacement')));
    }

    #[RunInSeparateProcess]
    public function testInvalidationReportsANativeTagIndexWriteFailure()
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public array $sets = []; public array $values = []; private array $tagVersions = []; public function get(string $key): mixed { return $this->values[$key] ?? false; } public function set(string $key, mixed $value): bool { $this->values[$key] = $value; return true; } public function eval(string $script, array $arguments = [], int $keyCount = 0): array|int|string|false { if (str_contains($script, "\'@generation:\' .. ARGV[1]")) { $this->tagVersions[$arguments[0]] = "@generation:".$arguments[1]; return 1; } if (str_contains($script, "ZRANGEBYSCORE\', KEYS[1], -1, -1")) { return $this->tagVersions[$arguments[0]] ?? false; } if (str_contains($script, "local removed = redis.call(\'ZREM\'")) { return false; } if (str_contains($script, "redis.call(\'ZADD\'")) { $this->sets[$arguments[0]][$arguments[1]] = $arguments[1]; return 1; } if (str_contains($script, "return redis.call(\'ZRANGEBYSCORE\'")) { return array_values($this->sets[$arguments[0]] ?? []); } return false; } public function del(string $key): int { unset($this->values[$key], $this->sets[$key], $this->tagVersions[$key]); return 1; } } }');

        $pool = new RedisCachePool(new \Redis());
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));

        self::assertFalse($pool->invalidateTag('tag'));
    }

    #[RunInSeparateProcess]
    public function testHierarchyDeleteReportsANativeIncrementFailure()
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public function get(string $key): false { return false; } public function incr(string $key): false { return false; } public function del(string $key): int { return 1; } } }');

        self::assertFalse((new RedisCachePool(new \Redis()))->deleteItem('|parent'));
    }

    #[DataProvider('invalidPayloadProvider')]
    #[RunInSeparateProcess]
    public function testInvalidBackendPayloadIsCacheMiss(mixed $payload)
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public mixed $payload; public function get(string $key): mixed { return $this->payload; } } }');

        $client = new \Redis();
        $client->payload = $payload;
        $item = (new RedisCachePool($client))->getItem('key');

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
