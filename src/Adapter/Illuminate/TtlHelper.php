<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Illuminate;

use Illuminate\Contracts\Cache\Store;
use ReflectionClass;

/**
 * This is the TTL helper class.
 *
 * @author Graham Campnell <graham@alt-three.com>
 */
class TtlHelper
{
    /**
     * Compute the TTL in the appropriate unit.
     *
     * @param int|null $seconds
     *
     * @return int
     */
    public static function computeTtl($seconds = null)
    {
        if ($seconds === null) {
            return 0;
        }

        return self::isLegacy() ? $seconds / 60 : $seconds;
    }

    /**
     * Determine if the store contract is pre-Laravel 5.8.
     *
     * @return bool
     */
    private static function isLegacy()
    {
        static $legacy;

        if ($legacy === null) {
            $params = (new ReflectionClass(Store::class))->getMethod('put')->getParameters();
            $legacy = $params[2]->getName() === 'minutes';
        }

        return $legacy;
    }
}
