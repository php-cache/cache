<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Common;

final class PhpUnserializer
{
    public static function unserialize(string $payload, mixed &$value): bool
    {
        try {
            return self::decodeWith(static fn (): mixed => @unserialize($payload), $value)
                && (false !== $value || 'b:0;' === $payload);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param \Closure(): mixed $decoder
     */
    public static function decodeWith(\Closure $decoder, mixed &$value): bool
    {
        $autoloadedClasses = [];
        $trackAutoload = static function (string $class) use (&$autoloadedClasses) {
            $autoloadedClasses[$class] = true;
        };
        spl_autoload_register($trackAutoload, true, true);

        try {
            try {
                $value = $decoder();
            } catch (\Throwable $exception) {
                if ($exception instanceof \Error || self::isUnserializationFailure($exception)) {
                    return false;
                }

                throw $exception;
            }
        } finally {
            spl_autoload_unregister($trackAutoload);
        }

        foreach (array_keys($autoloadedClasses) as $class) {
            if (!class_exists($class, false)) {
                return false;
            }
        }

        return true;
    }

    private static function isUnserializationFailure(\Throwable $exception): bool
    {
        foreach ($exception->getTrace() as $frame) {
            if (\in_array($frame['function'], ['unserialize', '__unserialize', '__wakeup'], true)) {
                return true;
            }
        }

        return false;
    }
}
