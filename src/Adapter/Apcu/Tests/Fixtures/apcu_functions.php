<?php

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
