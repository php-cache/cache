<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Apcu;

use Cache\Adapter\Apcu\Tests\ApcuFunctionStub;

function apcu_fetch(mixed $key, ?bool &$success = null): mixed
{
    $success = ApcuFunctionStub::$success;

    return ApcuFunctionStub::$storedValue;
}

function apcu_store(string $key, mixed $value, int $ttl = 0): bool
{
    ApcuFunctionStub::$storedKey = $key;
    ApcuFunctionStub::$storedValue = $value;
    ApcuFunctionStub::$storedTtl = $ttl;
    ApcuFunctionStub::$success = true;

    return true;
}
