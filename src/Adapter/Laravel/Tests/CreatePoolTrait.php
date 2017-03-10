<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Laravel\Tests;

use Illuminate\Cache\FileStore;
use Illuminate\Filesystem\Filesystem;
use Cache\Adapter\Laravel\LaravelCacheAdapter;

trait CreatePoolTrait
{
    private $laravelCache = null;

    public function createCachePool()
    {
        return $this->getLaravelCache();
    }

    private function getLaravelCache()
    {
        if ($this->laravelCache === null) {
            $this->laravelCache = new LaravelCacheAdapter(
                new FileStore(
                    new Filesystem(),
                    sys_get_temp_dir().'/swap-laravel-tests'
                )
            );
        }

        return $this->laravelCache;
    }

    public function createSimpleCache()
    {
        return $this->createCachePool();
    }
}
