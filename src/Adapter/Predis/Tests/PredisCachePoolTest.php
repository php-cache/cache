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
    public function testRepeatedTagSavesKeepOneIndexEntry()
    {
        $client = new PayloadClient(false);
        $pool = new PredisCachePool($client);

        self::assertTrue($pool->save($pool->getItem('key')->set('first')->setTags(['tag'])));
        self::assertTrue($pool->save($pool->getItem('key')->set('second')->setTags(['tag'])));
        self::assertSame(['key'], $client->smembers('tag!tag'));

        self::assertTrue($pool->invalidateTag('tag'));
        self::assertFalse($pool->hasItem('key'));
        self::assertSame([], $client->smembers('tag!tag'));
        self::assertSame([
            ['tag!tag', 'key'],
            ['tag!tag', 'key'],
        ], $client->setRemovalArguments);
    }

    public function testValidTaggedBackendPayloadIsHit()
    {
        $payload = serialize([true, 'value', ['tag' => 'tag'], null]);
        $item = (new PredisCachePool(new PayloadClient($payload)))->getItem('key');

        self::assertTrue($item->isHit());
        self::assertSame(['tag' => 'tag'], $item->getPreviousTags());
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
    public array $setRemovalArguments = [];
    private array $sets = [];
    private array $values = [];

    public function __construct(private readonly mixed $payload)
    {
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
            return $this->values[$arguments[0]] ?? $this->payload;
        }
        if ('set' === $method) {
            $this->values[$arguments[0]] = $arguments[1];

            return 'OK';
        }
        if ('sadd' === $method) {
            $added = 0;
            foreach ($arguments[1] as $value) {
                if (!isset($this->sets[$arguments[0]][$value])) {
                    $this->sets[$arguments[0]][$value] = $value;
                    ++$added;
                }
            }

            return $added;
        }
        if ('smembers' === $method) {
            return array_values($this->sets[$arguments[0]] ?? []);
        }
        if ('srem' === $method) {
            $this->setRemovalArguments[] = $arguments;
            $members = \is_array($arguments[1]) ? $arguments[1] : [$arguments[1]];
            $removed = 0;
            foreach ($members as $member) {
                if (isset($this->sets[$arguments[0]][$member])) {
                    unset($this->sets[$arguments[0]][$member]);
                    ++$removed;
                }
            }

            return $removed;
        }
        if ('del' === $method) {
            $keys = \is_array($arguments[0]) ? $arguments[0] : $arguments;
            $removed = 0;
            foreach ($keys as $key) {
                if (isset($this->sets[$key]) || isset($this->values[$key])) {
                    ++$removed;
                }
                unset($this->sets[$key], $this->values[$key]);
            }

            return $removed;
        }
        if ('incr' === $method) {
            return 1;
        }

        throw new \BadMethodCallException();
    }
}
