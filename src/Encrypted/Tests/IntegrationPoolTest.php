<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Encryption\Tests;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Encryption\EncryptedCachePool;
use Cache\IntegrationTests\CachePoolTest;
use Defuse\Crypto\Key;

class IntegrationPoolTest extends CachePoolTest
{
    private $cacheArray = [];

    protected function setUp()
    {
        parent::setUp();
    }

    public function createCachePool()
    {
        return new EncryptedCachePool(
            new ArrayCachePool(null, $this->cacheArray),
            Key::createNewRandomKey()
        );
    }
}
