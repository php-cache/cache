<?php

namespace Cache\Adapter\Chain\Tests;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Adapter\PHPArray\ArrayCachePool;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
trait CreatePoolTrait
{
    private $adapters;

    /**
     * @return mixed
     */
    public function getAdapters()
    {
        if ($this->adapters === null) {
            $filesystemAdapter = new Local(sys_get_temp_dir().'/cache_'.rand(1, 1000));
            $filesystem = new Filesystem($filesystemAdapter);
            $this->adapters = [new FilesystemCachePool($filesystem), new ArrayCachePool()];
        }

        return $this->adapters;
    }
}
