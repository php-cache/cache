<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Filesystem\Tests;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class FilesystemCachePoolTest extends \PHPUnit_Framework_TestCase
{
    use CreatePoolTrait;

    public function testCleanupOnExpire()
    {
        $cacheKey      = 'test_ttl_null';
        $cacheFilename = str_replace('=', '_', base64_encode($cacheKey));

        $pool = $this->createCachePool();

        $item = $pool->getItem($cacheKey);
        $item->set('data');
        $item->expiresAt(new \DateTime('now'));
        $pool->save($item);
        $this->assertTrue($this->getFilesystem()->has('cache/'.$cacheFilename));

        sleep(1);

        $item = $pool->getItem($cacheKey);
        $this->assertFalse($item->isHit());
        $this->assertFalse($this->getFilesystem()->has('cache/'.$cacheFilename));
    }

    public function testChangeFolder()
    {
        $pool = $this->createCachePool();
        $pool->setFolder('foobar');

        $pool->save($pool->getItem('test_path'));
        $this->assertTrue($this->getFilesystem()->has('foobar/'.str_replace('=', '_', base64_encode('test_path'))));
    }
}
