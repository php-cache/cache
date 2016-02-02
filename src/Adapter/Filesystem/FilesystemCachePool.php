<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Filesystem;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\Exception\InvalidArgumentException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use Psr\Cache\CacheItemInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class FilesystemCachePool extends AbstractCachePool
{
    const CACHE_PATH = 'cache';
    /**
     * @type Filesystem
     */
    private $filesystem;

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->filesystem->createDir(self::CACHE_PATH);
    }

    protected function fetchObjectFromCache($key)
    {
        $file = $this->getFilePath($key);
        if (!$this->filesystem->has($file)) {
            return [false, null];
        }

        $data = unserialize($this->filesystem->read($file));
        if ($data[0] !== null && time() > $data[0]) {
            $this->clearOneObjectFromCache($key);

            return [false, null];
        }

        return [true, $data[1]];
    }

    protected function clearAllObjectsFromCache()
    {
        $this->filesystem->deleteDir(self::CACHE_PATH);
        $this->filesystem->createDir(self::CACHE_PATH);

        return true;
    }

    protected function clearOneObjectFromCache($key)
    {
        try {
            return $this->filesystem->delete($this->getFilePath($key));
        } catch (FileNotFoundException $e) {
            return true;
        }
    }

    protected function storeItemInCache($key, CacheItemInterface $item, $ttl)
    {
        $file = $this->getFilePath($key);
        if ($this->filesystem->has($file)) {
            $this->filesystem->delete($file);
        }

        return $this->filesystem->write($file, serialize([
            ($ttl === null ? null : time() + $ttl),
            $item->get(),
        ]));
    }

    /**
     * @param string $key
     *
     * @throws InvalidArgumentException
     *
     * @return string
     */
    private function getFilePath($key)
    {
        if (!preg_match('|^[a-zA-Z0-9_\.! ]+$|', $key)) {
            throw new InvalidArgumentException(sprintf('Invalid key "%s". Valid keys must match [a-zA-Z0-9_\.! ].', $key));
        }

        return sprintf('%s/%s', self::CACHE_PATH, $key);
    }
}
