<?php

namespace Cache\Adapter\Apcu\Tests;

use Cache\Adapter\Apcu\ApcuCachePool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ApcuFunctionStub
{
    public static mixed $storedValue = null;

    public static bool $success = true;

    public static ?string $storedKey = null;

    public static ?int $storedTtl = null;
}

final class ApcuCachePoolTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSavedItemRoundTripsAsNativePayload()
    {
        require_once __DIR__.'/Fixtures/apcu_functions.php';

        ApcuFunctionStub::$storedValue = null;
        ApcuFunctionStub::$success = false;
        ApcuFunctionStub::$storedKey = null;
        ApcuFunctionStub::$storedTtl = null;

        $pool = new ApcuCachePool();
        $item = $pool->getItem('native');
        $item->set(['nested' => 'value']);

        self::assertTrue($pool->save($item));
        self::assertSame('native', ApcuFunctionStub::$storedKey);
        self::assertSame([['nested' => 'value'], [], null], ApcuFunctionStub::$storedValue);
        self::assertSame(0, ApcuFunctionStub::$storedTtl);

        $storedItem = (new ApcuCachePool())->getItem('native');

        self::assertTrue($storedItem->isHit());
        self::assertSame(['nested' => 'value'], $storedItem->get());
    }

    #[DataProvider('invalidPayloads')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCorruptPayloadIsACacheMiss(mixed $payload)
    {
        require_once __DIR__.'/Fixtures/apcu_functions.php';

        ApcuFunctionStub::$storedValue = $payload;
        ApcuFunctionStub::$success = true;

        self::assertFalse((new ApcuCachePool())->getItem('corrupt')->isHit());
    }

    public static function invalidPayloads(): iterable
    {
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'integer' => [42];
        yield 'array' => [[]];
        yield 'string' => ['not serialized'];
        yield 'legacy serialized payload' => [serialize(['value', [], null])];
        yield 'invalid tags' => [['value', [42], null]];
        yield 'invalid expiration' => [['value', [], 'tomorrow']];
    }
}
