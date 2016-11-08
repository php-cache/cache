<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2016 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */


namespace Cache\Adapter\Void\Tests;

use Cache\Adapter\Void\VoidCachePool;
use Cache\IntegrationTests\TaggableCachePoolTest;

class IntegrationTagTest extends TaggableCachePoolTest
{
    protected $skippedTests = [
        'testBasicUsage'                     => 'Void adapter does not save,',
        'testGetItems'                       => 'Void adapter does not save,',
        'testHasItem'                        => 'Void adapter does not save,',
        'testDeleteItem'                     => 'Void adapter does not save,',
        'testKeysWithDeferred'               => 'Void adapter does not save,',
        'testSaveDeferred'                   => 'Void adapter does not save,',
        'testMultipleTags'                   => 'Void adapter does not save,',
        'testRemoveTagWhenItemIsRemoved'     => 'Void adapter does not save,',
        'testClear'                          => 'Void adapter does not save,',
        'testClearTag'                       => 'Void adapter does not save,',
    ];

    public function createCachePool()
    {
        return new VoidCachePool();
    }
}
