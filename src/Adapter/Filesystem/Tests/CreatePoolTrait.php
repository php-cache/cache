<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Filesystem\Tests;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;

trait CreatePoolTrait
{
    private ?FilesystemOperator $filesystem = null;

    private ?string $rootPath = null;

    public function createCachePool(): FilesystemCachePool
    {
        return new FilesystemCachePool($this->getFilesystem());
    }

    public function createSimpleCache(): FilesystemCachePool
    {
        return $this->createCachePool();
    }

    protected function tearDown(): void
    {
        try {
            $this->removeRootPath();
        } finally {
            $this->filesystem = null;
            $this->rootPath = null;

            parent::tearDown();
        }
    }

    private function getFilesystem(): FilesystemOperator
    {
        if (null === $this->filesystem) {
            $this->filesystem = new Filesystem(new LocalFilesystemAdapter($this->getRootPath()));
        }

        return $this->filesystem;
    }

    private function getRootPath(): string
    {
        if (null === $this->rootPath) {
            $this->rootPath = sys_get_temp_dir().'/php-cache-filesystem-'.random_int(1, 100000);
        }

        return $this->rootPath;
    }

    private function removeRootPath(): void
    {
        if (null === $this->rootPath || !is_dir($this->rootPath)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->rootPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }

        rmdir($this->rootPath);
    }
}
