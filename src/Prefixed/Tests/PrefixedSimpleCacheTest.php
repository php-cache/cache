<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Cache\Prefixed\Tests;

use Psr\SimpleCache\CacheInterface;
use Cache\Prefixed\PrefixedSimpleCache;

/**
 * Description of PrefixedSimpleCacheTest
 *
 * @author ndobromirov
 */
class PrefixedSimpleCacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $method Method name to mock.
     * @param array $arguments List of expected arguments.
     * @param type $result
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getCacheStub($method, $arguments, $result)
    {
        $stub = $this->getMockBuilder(CacheInterface::class)
            ->setMethods(['get', 'set', 'delete', 'clear', 'getMultiple', 'setMultiple', 'deleteMultiple', 'has'])
            ->getMock()
            ->expects($this->once())
            ->method($method)
            ->willReturn($result);

        return call_user_func_array([$stub, 'with'], $arguments);
    }


    public function testGet()
    {
        $prefix = 'ns';
        $key = 'key';
        $returnValue = true;

        $stub = $this->getCacheStub('get', [$prefix . $key], $returnValue);
        $pool = (new PrefixedSimpleCache($stub, $prefix));

        $this->assertEquals($returnValue, $pool->get($key));
    }

    public function testSet()
    {
        $prefix = 'ns';
        $key = 'key';
        $returnValue = true;
        $value = 'value';

        $stub = $this->getCacheStub('set', [$prefix . $key, $value], $returnValue);
        $pool = (new PrefixedSimpleCache($stub, $prefix));

        $this->assertEquals($returnValue, $pool->set($key, $value));
    }

    public function testDelete()
    {
        $prefix = 'ns';
        $key = 'key';
        $returnValue = true;

        $stub = $this->getCacheStub('delete', [[$prefix . $key]], $returnValue);
        $pool = (new PrefixedSimpleCache($stub, $prefix));

        $this->assertEquals($returnValue, $pool->delete($key));
    }

    public function testClear()
    {
        $prefix = 'ns';
        $returnValue = true;

        $stub = $this->getCacheStub('clear', [], $returnValue);
        $pool = (new PrefixedSimpleCache($stub, $prefix));

        $this->assertEquals($returnValue, $pool->clear());
    }

    public function testGetMultiple()
    {
        $prefix = 'ns';
        list ($key1, $value1) = ['key1', 1];
        list ($key2, $value2) = ['key2', 2];

        $stub = $this->getCacheStub('getMultiple', [[$prefix . $key1, $prefix . $key2]], [
            $prefix . $key1 => $value1,
            $prefix . $key2 => $value2,
        ]);
        $pool = new PrefixedSimpleCache($stub, $prefix);

        $this->assertEquals([$key1 => $value1, $key2 => $value2], $pool->getMultiple([$key1, $key2]));
    }

    public function testSetMultiple()
    {
        $prefix = 'ns';
        list ($key1, $value1) = ['key1', 1];
        list ($key2, $value2) = ['key2', 2];
        $result = true;

        $stub = $this->getCacheStub('setMultiple', [[$prefix . $key1 => $value1, $prefix . $key2 => $value2]], $result);
        $pool = new PrefixedSimpleCache($stub, $prefix);

        $this->assertEquals($result, $pool->setMultiple([$key1 => $value1, $key2 => $value2]));
    }

    public function testDeleteMultiple()
    {
        $prefix = 'ns';
        list ($key1, $key2) = ['key1', 'key2'];
        $result = true;

        $stub = $this->getCacheStub('deleteMultiple', [[$prefix . $key1, $prefix . $key2]], $result);
        $pool = new PrefixedSimpleCache($stub, $prefix);

        $this->assertEquals($result, $pool->deleteMultiple([$key1, $key2]));
    }

    public function testHas()
    {
        $prefix = 'ns';
        $key = 'key';
        $result = true;

        $stub = $this->getCacheStub('has', [[$prefix . $key]], $result);
        $pool = new PrefixedSimpleCache($stub, $prefix);

        $this->assertEquals($result, $pool->has($key));
    }
}
