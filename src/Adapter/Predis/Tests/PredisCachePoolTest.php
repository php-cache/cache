<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Predis\Tests;

use Cache\Adapter\Predis\PredisCachePool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Predis\ClientInterface;
use Predis\Command\CommandInterface;

class PredisCachePoolTest extends TestCase
{
    public function testTagInvalidationReportsAScriptReadFailure()
    {
        $pool = new PredisCachePool(new PayloadClient(false, false));

        self::assertFalse($pool->invalidateTag('tag'));
    }

    public function testValidTaggedBackendPayloadIsHit()
    {
        $payload = serialize([true, 'value', [['tag', 'version']], null]);
        $item = (new PredisCachePool(new PayloadClient($payload, '@generation:version')))->getItem('key');

        self::assertTrue($item->isHit());
        self::assertSame(['tag' => 'tag'], $item->getPreviousTags());
        self::assertSame([['tag', 'version']], $item->getTagVersions());
    }

    #[DataProvider('invalidPayloadProvider')]
    public function testInvalidBackendPayloadIsCacheMiss(mixed $payload)
    {
        $item = (new PredisCachePool(new PayloadClient($payload)))->getItem('key');

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

final class PayloadClient implements ClientInterface
{
    public function __construct(
        private readonly mixed $payload,
        private readonly mixed $evalResponse = null,
    ) {
    }

    public function getCommandFactory()
    {
        throw new \BadMethodCallException();
    }

    public function getOptions()
    {
        throw new \BadMethodCallException();
    }

    public function connect()
    {
        throw new \BadMethodCallException();
    }

    public function disconnect()
    {
        throw new \BadMethodCallException();
    }

    public function getConnection()
    {
        throw new \BadMethodCallException();
    }

    public function createCommand($method, $arguments = [])
    {
        throw new \BadMethodCallException();
    }

    public function executeCommand(CommandInterface $command)
    {
        throw new \BadMethodCallException();
    }

    public function __call($method, $arguments)
    {
        if ('get' === $method) {
            return $this->payload;
        }
        if ('eval' === $method) {
            return $this->evalResponse;
        }
        if ('del' === $method) {
            return 0;
        }

        throw new \BadMethodCallException();
    }
}
