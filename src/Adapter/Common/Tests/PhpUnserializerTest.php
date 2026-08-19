<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Common\Tests;

use Cache\Adapter\Common\PhpUnserializer;
use Cache\Adapter\Common\Tests\Fixtures\AutoloadedValue;
use PHPUnit\Framework\TestCase;

final class PhpUnserializerTest extends TestCase
{
    public function testRejectsMalformedPayloadThatDecodesToFalse()
    {
        set_error_handler(static fn (): bool => true);

        try {
            self::assertFalse(PhpUnserializer::unserialize('not serialized', $value));
        } finally {
            restore_error_handler();
        }
    }

    public function testAcceptsSerializedFalse()
    {
        self::assertTrue(PhpUnserializer::unserialize('b:0;', $value));
        self::assertFalse($value);
    }

    public function testRejectsIncompleteClassInInternalObjectStorage()
    {
        $payload = str_replace('stdClass', 'GoneType', serialize(new \ArrayObject([new \stdClass()])));

        self::assertFalse(PhpUnserializer::unserialize($payload, $value));
    }

    public function testAcceptsClassResolvedByAutoloader()
    {
        $class = AutoloadedValue::class;
        self::assertFalse(class_exists($class, false));
        $autoload = static function (string $requestedClass) use ($class) {
            if ($class === $requestedClass) {
                require_once __DIR__.'/Fixtures/AutoloadedValue.php';
            }
        };
        spl_autoload_register($autoload);

        try {
            $payload = \sprintf('O:%d:"%s":0:{}', \strlen($class), $class);

            self::assertTrue(PhpUnserializer::unserialize($payload, $value));
            self::assertInstanceOf($class, $value);
        } finally {
            spl_autoload_unregister($autoload);
        }
    }

    public function testRejectsReserializedIncompleteClass()
    {
        $incomplete = @unserialize('O:8:"GoneType":0:{}');

        self::assertFalse(PhpUnserializer::unserialize(serialize($incomplete), $value));
    }
}
