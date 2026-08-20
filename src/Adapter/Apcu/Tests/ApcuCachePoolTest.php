<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Apcu\Tests;

use Cache\Adapter\Apcu\ApcuCachePool;
use Cache\Adapter\Common\Exception\CachePoolException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ApcuFunctionStub
{
    public static ?\Throwable $exception = null;

    public static ?string $missingClass = null;

    public static ?string $serializedValue = null;

    public static mixed $storedValue = null;

    public static bool $success = true;

    public static bool $throwUnserializationException = false;

    public static bool $deleteResult = false;

    public static bool $exists = false;

    public static ?string $storedKey = null;

    public static ?int $storedTtl = null;
}

final class ApcuUnserializationFailure
{
    public function __unserialize(array $data)
    {
        throw new \Error('cached payload could not be decoded');
    }
}

final class ApcuUnserializationException
{
    public function __unserialize(array $data)
    {
        throw new \RuntimeException('cached payload could not be decoded');
    }
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

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testIncompleteClassPayloadIsACacheMiss()
    {
        require_once __DIR__.'/Fixtures/apcu_functions.php';

        ApcuFunctionStub::$missingClass = 'GoneType';
        ApcuFunctionStub::$storedValue = ['value', [], null];
        ApcuFunctionStub::$success = true;

        self::assertFalse((new ApcuCachePool())->getItem('incomplete')->isHit());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testUnserializationErrorIsACacheMiss()
    {
        require_once __DIR__.'/Fixtures/apcu_functions.php';

        ApcuFunctionStub::$serializedValue = serialize([new ApcuUnserializationFailure(), [], null]);
        ApcuFunctionStub::$success = true;

        self::assertFalse((new ApcuCachePool())->getItem('broken')->isHit());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testUnserializationExceptionIsACacheMiss()
    {
        require_once __DIR__.'/Fixtures/apcu_functions.php';

        ApcuFunctionStub::$storedValue = ['value', [], null];
        ApcuFunctionStub::$success = true;
        ApcuFunctionStub::$throwUnserializationException = true;

        self::assertFalse((new ApcuCachePool())->getItem('broken')->isHit());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBackendFetchExceptionIsNotTreatedAsCacheMiss()
    {
        require_once __DIR__.'/Fixtures/apcu_functions.php';

        $backendException = new \RuntimeException('backend failed');
        ApcuFunctionStub::$exception = $backendException;

        try {
            (new ApcuCachePool())->getItem('key')->isHit();
            self::fail('The backend exception was not propagated.');
        } catch (CachePoolException $exception) {
            self::assertSame($backendException, $exception->getPrevious());
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDeleteReportsANativeFailureUnlessTheKeyIsMissing()
    {
        require_once __DIR__.'/Fixtures/apcu_functions.php';

        $pool = new class extends ApcuCachePool {
            public function clearKey(string $key): bool
            {
                return $this->clearOneObjectFromCache($key);
            }
        };
        ApcuFunctionStub::$deleteResult = false;
        ApcuFunctionStub::$exists = true;
        self::assertFalse($pool->clearKey('key'));

        ApcuFunctionStub::$exists = false;
        self::assertTrue($pool->clearKey('missing'));
    }
}
