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
    public function testConstructorWithInvalidObject(): void
    {
        $this->expectException(CachePoolException::class);

        new RedisCachePool(new \stdClass());
    }

    #[RunInSeparateProcess]
    public function testRepeatedTagSavesKeepOneIndexEntry(): void
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval(<<<'PHP'
            namespace {
                class Redis
                {
                    public array $setRemovalArguments = [];
                    public array $sets = [];
                    public array $values = [];

                    public function del(string $key): int
                    {
                        $exists = isset($this->sets[$key]) || isset($this->values[$key]);
                        unset($this->sets[$key], $this->values[$key]);

                        return (int) $exists;
                    }

                    public function get(string $key): mixed
                    {
                        return $this->values[$key] ?? false;
                    }

                    public function sAdd(string $key, string ...$values): int
                    {
                        $added = 0;
                        foreach ($values as $value) {
                            if (!isset($this->sets[$key][$value])) {
                                $this->sets[$key][$value] = $value;
                                ++$added;
                            }
                        }

                        return $added;
                    }

                    public function sMembers(string $key): array
                    {
                        return array_values($this->sets[$key] ?? []);
                    }

                    public function sRem(string $key, string ...$values): int
                    {
                        $this->setRemovalArguments[] = func_get_args();
                        $removed = 0;
                        foreach ($values as $value) {
                            if (isset($this->sets[$key][$value])) {
                                unset($this->sets[$key][$value]);
                                ++$removed;
                            }
                        }

                        return $removed;
                    }

                    public function set(string $key, mixed $value): bool
                    {
                        $this->values[$key] = $value;

                        return true;
                    }
                }
            }
            PHP);

        $client = new \Redis();
        $pool = new RedisCachePool($client);

        self::assertTrue($pool->save($pool->getItem('key')->set('first')->setTags(['tag'])));
        self::assertTrue($pool->save($pool->getItem('key')->set('second')->setTags(['tag'])));
        self::assertSame(['key'], $client->sMembers('tag!tag'));

        self::assertTrue($pool->invalidateTag('tag'));
        self::assertFalse($pool->hasItem('key'));
        self::assertSame([], $client->sMembers('tag!tag'));
        self::assertSame([
            ['tag!tag', 'key'],
            ['tag!tag', 'key'],
        ], $client->setRemovalArguments);
    }

    #[RunInSeparateProcess]
    public function testValidTaggedBackendPayloadIsHit(): void
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public mixed $payload; public function get(string $key): mixed { return $this->payload; } } }');

        $client = new \Redis();
        $client->payload = serialize([true, 'value', ['tag' => 'tag'], null]);
        $item = (new RedisCachePool($client))->getItem('key');

        self::assertTrue($item->isHit());
        self::assertSame(['tag' => 'tag'], $item->getPreviousTags());
    }

    #[RunInSeparateProcess]
    public function testDeleteReportsANativeFailure(): void
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public function get(string $key): false { return false; } public function del(string $key): false { return false; } } }');

        self::assertFalse((new RedisCachePool(new \Redis()))->deleteItem('key'));
    }

    #[RunInSeparateProcess]
    public function testTagInvalidationReportsANativeListDeleteFailure(): void
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public function sMembers(string $key): array { return []; } public function del(string $key): false { return false; } } }');

        self::assertFalse((new RedisCachePool(new \Redis()))->invalidateTag('tag'));
    }

    #[RunInSeparateProcess]
    public function testTagInvalidationReportsANativeListReadFailure(): void
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public function sMembers(string $key): false { return false; } public function del(string $key): int { return 0; } } }');

        self::assertFalse((new RedisCachePool(new \Redis()))->invalidateTag('tag'));
    }

    #[RunInSeparateProcess]
    public function testSaveReportsANativeTagIndexWriteFailure(): void
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public function get(string $key): false { return false; } public function set(string $key, mixed $value): bool { return true; } public function sAdd(string $key, string ...$values): false { return false; } } }');

        $pool = new RedisCachePool(new \Redis());

        self::assertFalse($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));
    }

    #[RunInSeparateProcess]
    public function testDeleteReportsANativeTagIndexWriteFailure(): void
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public array $values = []; public function get(string $key): mixed { return $this->values[$key] ?? false; } public function set(string $key, mixed $value): bool { $this->values[$key] = $value; return true; } public function sAdd(string $key, string ...$values): int { return 1; } public function sRem(string $key, string ...$values): false { return false; } public function del(string $key): int { unset($this->values[$key]); return 1; } } }');

        $pool = new RedisCachePool(new \Redis());
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));

        self::assertFalse($pool->deleteItem('key'));
    }

    #[RunInSeparateProcess]
    public function testSaveReportsANativePreviousTagIndexRemovalFailure(): void
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public array $values = []; public bool $failTagRemoval = false; public function get(string $key): mixed { return $this->values[$key] ?? false; } public function set(string $key, mixed $value): bool { $this->values[$key] = $value; return true; } public function sAdd(string $key, string ...$values): int { return 1; } public function sRem(string $key, string ...$values): int|false { return $this->failTagRemoval ? false : 1; } } }');

        $client = new \Redis();
        $pool = new RedisCachePool($client);
        self::assertTrue($pool->save($pool->getItem('key')->set('original')->setTags(['tag'])));
        $client->failTagRemoval = true;

        self::assertFalse($pool->save($pool->getItem('key')->set('replacement')));
    }

    #[RunInSeparateProcess]
    public function testInvalidationReportsANativeTagIndexWriteFailure(): void
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public array $sets = []; public array $values = []; public function get(string $key): mixed { return $this->values[$key] ?? false; } public function set(string $key, mixed $value): bool { $this->values[$key] = $value; return true; } public function sAdd(string $key, string ...$values): int { foreach ($values as $value) { $this->sets[$key][$value] = $value; } return 1; } public function sMembers(string $key): array { return array_values($this->sets[$key] ?? []); } public function sRem(string $key, string ...$values): false { return false; } public function del(string $key): int { unset($this->values[$key], $this->sets[$key]); return 1; } } }');

        $pool = new RedisCachePool(new \Redis());
        self::assertTrue($pool->save($pool->getItem('key')->set('value')->setTags(['tag'])));

        self::assertFalse($pool->invalidateTag('tag'));
    }

    #[RunInSeparateProcess]
    public function testHierarchyDeleteReportsANativeIncrementFailure(): void
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('This test uses a local Redis stub.');
        }

        eval('namespace { class Redis { public function get(string $key): false { return false; } public function incr(string $key): false { return false; } public function del(string $key): int { return 1; } } }');

        self::assertFalse((new RedisCachePool(new \Redis()))->deleteItem('|parent'));
    }

    #[DataProvider('invalidPayloadProvider')]
    #[RunInSeparateProcess]
    public function testInvalidBackendPayloadIsCacheMiss(mixed $payload): void
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
    }
}
