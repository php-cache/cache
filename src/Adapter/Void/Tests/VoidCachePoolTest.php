<?php

declare(strict_types=1);

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
