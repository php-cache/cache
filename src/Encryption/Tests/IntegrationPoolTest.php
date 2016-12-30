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

use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Encryption\EncryptedCachePool;
use Cache\IntegrationTests\CachePoolTest;
use Defuse\Crypto\Key;

class IntegrationPoolTest extends CachePoolTest
{
    private $cacheArray = [];

    public function createCachePool()
    {
        return new EncryptedCachePool(
            new ArrayCachePool(null, $this->cacheArray),
            Key::loadFromAsciiSafeString('def000007c57b06c65b0df4bcac939924e42605d8d76e1462b619318bf94107c28db30c5394b4242db5e45563e1226cffcdff8123fa214ea1fcc4aa10b0ddb1b4a587b7e')
        );
    }
}
