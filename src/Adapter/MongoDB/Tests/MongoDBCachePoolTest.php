<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\MongoDB\Tests;

use Cache\Adapter\MongoDB\MongoDBCachePool;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\UpdateResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class MongoDBCachePoolTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testExpiringItemCanBeOverwrittenWithoutExpiration()
    {
        if (!class_exists(UTCDateTime::class)) {
            eval('namespace MongoDB\\BSON { class UTCDateTime { public function __construct(public int $milliseconds) {} } }');
        }

        $collection = $this->createMock(Collection::class);
        $result = $this->createStub(UpdateResult::class);
        $updates = [];
        $collection->expects($this->exactly(2))
            ->method('updateOne')
            ->willReturnCallback(static function (array $filter, array $update) use (&$updates, $result): UpdateResult {
                $updates[] = $update;

                return $result;
            });

        $pool = new MongoDBCachePool($collection);
        $item = $pool->getItem('key')->set('value')->expiresAfter(60);
        $pool->save($item);
        $item->expiresAfter(null);
        $pool->save($item);

        self::assertInstanceOf(UTCDateTime::class, $updates[0]['$set']['expiresAt'] ?? null);
        self::assertSame(['expiresAt' => true], $updates[1]['$unset'] ?? null);
    }

    public function testValidTaggedBackendDocumentIsHit()
    {
        $tagVersionKey = 'tagv!'.substr(hash('sha256', 'tag'), 0, 59);
        $collection = $this->createMock(Collection::class);
        $collection->method('findOne')->willReturnCallback(static function (array $filter) use ($tagVersionKey): array {
            if ($tagVersionKey === $filter['_id']) {
                return [
                    'data' => serialize('version'),
                    'tags' => serialize([]),
                    'expirationTimestamp' => null,
                ];
            }

            return [
                'data' => serialize('value'),
                'tags' => serialize([['tag', 'version']]),
                'expirationTimestamp' => null,
            ];
        });

        $item = (new MongoDBCachePool($collection))->getItem('key');

        self::assertTrue($item->isHit());
        self::assertSame(['tag' => 'tag'], $item->getPreviousTags());
        self::assertSame([['tag', 'version']], $item->getTagVersions());
    }

    #[DataProvider('invalidDocumentProvider')]
    public function testInvalidBackendDocumentIsCacheMiss(array|object|null $document)
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('findOne')->willReturn($document);

        $item = (new MongoDBCachePool($collection))->getItem('key');

        self::assertFalse($item->isHit());
        self::assertNull($item->get());
    }

    /**
     * @return iterable<string, array{array<string, mixed>|null}>
     */
    public static function invalidDocumentProvider(): iterable
    {
        yield 'missing' => [null];
        yield 'missing data' => [['tags' => serialize([])]];
        yield 'non-string data' => [['data' => false, 'tags' => serialize([])]];
        yield 'malformed data' => [['data' => 'not serialized', 'tags' => serialize([])]];
        yield 'malformed tags' => [['data' => serialize('value'), 'tags' => 'not serialized']];
        yield 'throwing unserialize' => [['data' => serialize(new ThrowingSerializedValue()), 'tags' => serialize([])]];
        yield 'incomplete class' => [[
            'data' => str_replace('stdClass', 'GoneType', serialize(new \stdClass())),
            'tags' => serialize([]),
        ]];
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
