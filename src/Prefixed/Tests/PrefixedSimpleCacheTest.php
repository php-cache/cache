<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Prefixed\Tests;

use Cache\Prefixed\PrefixedSimpleCache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * Description of PrefixedSimpleCacheTest.
 *
 * @author ndobromirov
 */
class PrefixedSimpleCacheTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidIterableKeyProvider(): iterable
    {
        yield 'coercible scalar' => [true];
        yield 'object' => [new \stdClass()];
    }

    /**
     * @param string $method    method name to mock
     * @param array  $arguments list of expected arguments
     * @param type   $result
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getCacheStub($method, $arguments, $result)
    {
        $stub = $this->getMockBuilder(CacheInterface::class)
            ->onlyMethods(['get', 'set', 'delete', 'clear', 'getMultiple', 'setMultiple', 'deleteMultiple', 'has'])
            ->getMock();

        $invocation = $stub->expects($this->once())->method($method);
        \call_user_func_array([$invocation->willReturn($result), 'with'], $arguments);

        return $stub;
    }

    public function testGet()
    {
        $prefix = 'ns';
        $key = 'key';
        $result = true;

        $stub = $this->getCacheStub('get', [$prefix.$key], $result);
        $pool = new PrefixedSimpleCache($stub, $prefix);

        $this->assertEquals($result, $pool->get($key));
    }

    public function testReservedCharactersInPrefixAreEncoded()
    {
        $stub = $this->getCacheStub('get', ['_x7B__x7D__x28__x29__x2F__x5C__x40__x3A__x25__x7C__x21_key'], 'value');
        $pool = new PrefixedSimpleCache($stub, '{}()/\\@:%|!');

        $this->assertSame('value', $pool->get('key'));
    }

    public function testEncodedPrefixUsesOnlyPortablePsrCharacters()
    {
        $backend = $this->createMock(CacheInterface::class);
        $backend->expects($this->once())
            ->method('get')
            ->with($this->callback(static fn (string $key): bool => 1 === preg_match('/^[A-Za-z0-9_.]+$/D', $key)), null)
            ->willReturn('value');

        $this->assertSame('value', (new PrefixedSimpleCache($backend, '{'))->get('key'));
    }

    public function testSet()
    {
        $prefix = 'ns';
        $key = 'key';
        $value = 'value';
        $result = true;

        $stub = $this->getCacheStub('set', [$prefix.$key, $value], $result);
        $pool = new PrefixedSimpleCache($stub, $prefix);

        $this->assertEquals($result, $pool->set($key, $value));
    }

    public function testDelete()
    {
        $prefix = 'ns';
        $key = 'key';
        $result = true;

        $stub = $this->getCacheStub('delete', [$prefix.$key], $result);
        $pool = new PrefixedSimpleCache($stub, $prefix);

        $this->assertEquals($result, $pool->delete($key));
    }

    public function testClear()
    {
        $prefix = 'ns';
        $result = true;

        $stub = $this->getCacheStub('clear', [], $result);
        $pool = new PrefixedSimpleCache($stub, $prefix);

        $this->assertEquals($result, $pool->clear());
    }

    public function testGetMultiple()
    {
        $prefix = 'ns';
        list($key1, $value1) = ['key1', 1];
        list($key2, $value2) = ['key2', 2];

        $stub = $this->getCacheStub('getMultiple', [[$prefix.$key1, $prefix.$key2]], [
            $prefix.$key1 => $value1,
            $prefix.$key2 => $value2,
        ]);
        $pool = new PrefixedSimpleCache($stub, $prefix);

        $this->assertEquals([$key1 => $value1, $key2 => $value2], iterator_to_array($pool->getMultiple([$key1, $key2])));
    }

    public function testGetMultiplePreservesNumericStringKeysWithAnEmptyPrefix()
    {
        $stub = $this->getCacheStub('getMultiple', [['123']], [123 => 'value']);
        $pool = new PrefixedSimpleCache($stub, '');

        $keys = [];
        foreach ($pool->getMultiple(['123']) as $key => $value) {
            $keys[] = $key;
            $this->assertSame('value', $value);
        }

        $this->assertSame(['123'], $keys);
    }

    #[DataProvider('invalidIterableKeyProvider')]
    public function testGetMultipleRejectsNonStringKeys(mixed $key)
    {
        $pool = new PrefixedSimpleCache($this->createMock(CacheInterface::class), 'ns');

        $this->expectException(\Psr\SimpleCache\InvalidArgumentException::class);

        $pool->getMultiple([$key]);
    }

    public function testSetMultiple()
    {
        $prefix = 'ns';
        list($key1, $value1) = ['key1', 1];
        list($key2, $value2) = ['key2', 2];
        $result = true;

        $stub = $this->createMock(CacheInterface::class);
        $stub->expects($this->once())
            ->method('setMultiple')
            ->with($this->callback(static fn (iterable $values): bool => [
                $prefix.$key1 => $value1,
                $prefix.$key2 => $value2,
            ] === iterator_to_array($values)), null)
            ->willReturn($result);
        $pool = new PrefixedSimpleCache($stub, $prefix);

        $this->assertEquals($result, $pool->setMultiple([$key1 => $value1, $key2 => $value2]));
    }

    public function testSetMultiplePreservesNumericStringGeneratorKeysWithAnEmptyPrefix()
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('setMultiple')
            ->with($this->callback(function (iterable $values): bool {
                foreach ($values as $key => $value) {
                    return '123' === $key && 'value' === $value;
                }

                return false;
            }), null)
            ->willReturn(true);

        $values = static function (): \Generator {
            yield '123' => 'value';
        };

        $this->assertTrue((new PrefixedSimpleCache($cache, ''))->setMultiple($values()));
    }

    #[DataProvider('invalidIterableKeyProvider')]
    public function testSetMultipleRejectsNonStringGeneratorKeys(mixed $key)
    {
        $pool = new PrefixedSimpleCache($this->createMock(CacheInterface::class), 'ns');
        $values = static function () use ($key): \Generator {
            yield $key => 'value';
        };

        $this->expectException(\Psr\SimpleCache\InvalidArgumentException::class);

        $pool->setMultiple($values());
    }

    public function testDeleteMultiple()
    {
        $prefix = 'ns';
        list($key1, $key2) = ['key1', 'key2'];
        $result = true;

        $stub = $this->getCacheStub('deleteMultiple', [[$prefix.$key1, $prefix.$key2]], $result);
        $pool = new PrefixedSimpleCache($stub, $prefix);

        $this->assertEquals($result, $pool->deleteMultiple([$key1, $key2]));
    }

    #[DataProvider('invalidIterableKeyProvider')]
    public function testDeleteMultipleRejectsNonStringKeys(mixed $key)
    {
        $pool = new PrefixedSimpleCache($this->createMock(CacheInterface::class), 'ns');

        $this->expectException(\Psr\SimpleCache\InvalidArgumentException::class);

        $pool->deleteMultiple([$key]);
    }

    public function testHas()
    {
        $prefix = 'ns';
        $key = 'key';
        $result = true;

        $stub = $this->getCacheStub('has', [$prefix.$key], $result);
        $pool = new PrefixedSimpleCache($stub, $prefix);

        $this->assertEquals($result, $pool->has($key));
    }
}
