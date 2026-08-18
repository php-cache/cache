<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Encryption\Tests;

use Cache\Adapter\Common\CacheItem;
use Cache\Adapter\Common\Exception\InvalidArgumentException;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Encryption\EncryptedCachePool;
use Cache\IntegrationTests\CachePoolTest;
use Defuse\Crypto\Key;
use Psr\Cache\CacheItemPoolInterface;

class IntegrationPoolTest extends CachePoolTest
{
    private array $storage = [];

    protected array $skippedTests = [
        'testBasicUsageWithLongKey' => 'Long keys are not supported.',
    ];

    /**
     * @throws \Defuse\Crypto\Exception\BadFormatException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function createCachePool(): CacheItemPoolInterface
    {
        return new EncryptedCachePool(
            new ArrayCachePool(null, $this->storage),
            Key::loadFromAsciiSafeString('def000007c57b06c65b0df4bcac939924e42605d8d76e1462b619318bf94107c28db30c5394b4242db5e45563e1226cffcdff8123fa214ea1fcc4aa10b0ddb1b4a587b7e')
        );
    }

    public function testSaveToThrowAInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);

        $pool = $this->createCachePool();

        $pool->save(new CacheItem('save_valid_exceptiond'));
    }

    public function testSaveDeferredToThrowAInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);

        $pool = $this->createCachePool();

        $pool->saveDeferred(new CacheItem('save_valid_exceptiond'));
    }

    public function testSaveRejectsAnEncryptedItemFromAnotherPool()
    {
        $pool = $this->createCachePool();
        $otherPool = $this->createCachePool();

        $this->expectException(InvalidArgumentException::class);

        $pool->save($otherPool->getItem('key')->set('value'));
    }

    public function testSaveDeferredRejectsAnEncryptedItemFromAnotherPool()
    {
        $pool = $this->createCachePool();
        $otherPool = $this->createCachePool();

        $this->expectException(InvalidArgumentException::class);

        $pool->saveDeferred($otherPool->getItem('key')->set('value'));
    }

    public function testEncryptedPoolsCanBeNested()
    {
        $innerPool = $this->createCachePool();
        $outerPool = new EncryptedCachePool($innerPool, Key::createNewRandomKey());

        $item = $outerPool->getItem('key');
        $item->set('value');
        $this->assertTrue($outerPool->save($item));
        $this->assertSame('value', $outerPool->getItem('key')->get());
    }
}
