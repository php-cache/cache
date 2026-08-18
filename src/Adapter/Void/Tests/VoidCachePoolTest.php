<?php

declare(strict_types=1);

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Void\Tests;

use Cache\Adapter\Void\VoidCachePool;
use PHPUnit\Framework\TestCase;

final class VoidCachePoolTest extends TestCase
{
    public function testTagOperationsSucceed()
    {
        $pool = new VoidCachePool();
        $item = $pool->getItem('key')->set('value')->setTags(['tag']);

        self::assertTrue($pool->save($item));
        self::assertTrue($pool->invalidateTag('tag'));
        self::assertTrue($pool->clearTags(['tag']));
    }
}
