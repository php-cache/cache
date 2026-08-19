<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Filesystem;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\Exception\InvalidArgumentException;
use Cache\Adapter\Common\PhpCacheItem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class FilesystemCachePool extends AbstractCachePool
{
    private FilesystemOperator $filesystem;

    /**
     * The folder should not begin nor end with a slash. Example: path/to/cache.
     */
    private string $folder;

    public function __construct(FilesystemOperator $filesystem, string $folder = 'cache')
    {
        $this->setFolder($folder);
        $this->setFilesystem($filesystem);
    }

    public function getFilesystem(): FilesystemOperator
    {
        return $this->filesystem;
    }

    public function setFilesystem(FilesystemOperator $filesystem): void
    {
        $this->filesystem = $filesystem;
        $this->filesystem->createDirectory($this->folder);
    }

    public function getFolder(): string
    {
        return $this->folder;
    }

    public function setFolder(string $folder): void
    {
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $folder)) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                throw new InvalidArgumentException('The cache folder must not traverse above its configured path.');
            }

            $segments[] = $segment;
        }

        if ([] === $segments) {
            throw new InvalidArgumentException('The cache folder must not resolve to the filesystem root.');
        }

        $this->folder = implode('/', $segments);
    }

    protected function validateKey(mixed $key): string
    {
        return $this->validateFilename(parent::validateKey($key));
    }

    /**
     * @return array{bool, mixed, array<string, string>, int|null}
     */
    protected function fetchObjectFromCache(string $key): array
    {
        $empty = [false, null, [], null];
        $file = $this->getFilePath($key);

        try {
            $data = $this->decodeCacheItem($this->filesystem->read($file));
            if (null === $data) {
                return $empty;
            }
        } catch (UnableToReadFile $e) {
            return $empty;
        }

        // Determine expirationTimestamp from data, remove items if expired
        $expirationTimestamp = $data[2] ?: null;
        if (null !== $expirationTimestamp && time() >= $expirationTimestamp) {
            foreach ($data[1] as $tag) {
                $this->removeListItem($this->getTagKey($tag), $key);
            }
            $this->forceClear($key);

            return $empty;
        }

        return [true, $data[0], $data[1], $expirationTimestamp];
    }

    protected function clearAllObjectsFromCache(): bool
    {
        foreach ($this->filesystem->listContents($this->folder) as $entry) {
            if ($entry->isFile()) {
                $this->deleteFile($entry->path());
            }
        }

        return true;
    }

    protected function clearOneObjectFromCache(string $key): bool
    {
        return $this->forceClear($key);
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        $data = serialize(
            [
                $item->get(),
                $item->getTags(),
                $item->getExpirationTimestamp(),
            ]
        );

        $this->filesystem->write($this->getFilePath($item->getKey()), $data);

        return true;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getFilePath(string $key): string
    {
        $key = $this->validateFilename($key);
        // PSR-6 requires dot keys; @ is reserved, so these storage names cannot collide with valid keys.
        $key = match ($key) {
            '.' => '@dot',
            '..' => '@dotdot',
            default => $key,
        };

        return \sprintf('%s/%s', $this->folder, $key);
    }

    private function validateFilename(string $key): string
    {
        if (!preg_match('|^[a-zA-Z0-9_\.! ]+$|', $key)) {
            throw new InvalidArgumentException(\sprintf('Invalid key "%s". Valid filenames must match [a-zA-Z0-9_\.! ].', $key));
        }

        return $key;
    }

    /**
     * @return list<string>
     */
    protected function getList(string $name): array
    {
        $file = $this->getFilePath($name);

        try {
            return $this->decodeList($this->filesystem->read($file));
        } catch (UnableToReadFile $e) {
            return [];
        }
    }

    protected function removeList(string $name): bool
    {
        return $this->deleteFile($this->getFilePath($name));
    }

    protected function appendListItem(string $name, string $key): bool
    {
        $list = $this->getList($name);
        $list[] = $key;

        $this->filesystem->write($this->getFilePath($name), serialize($list));

        return true;
    }

    protected function removeListItem(string $name, string $key): bool
    {
        $list = $this->getList($name);
        foreach ($list as $i => $item) {
            if ($item === $key) {
                unset($list[$i]);
            }
        }

        $this->filesystem->write($this->getFilePath($name), serialize($list));

        return true;
    }

    private function forceClear(string $key): bool
    {
        return $this->deleteFile($this->getFilePath($key));
    }

    private function deleteFile(string $file): bool
    {
        try {
            $this->filesystem->delete($file);
        } catch (UnableToDeleteFile $e) {
            if ($this->filesystem->fileExists($file)) {
                throw $e;
            }
        }

        return true;
    }

    /**
     * @return array{mixed, array<string, string>, int|null}|null
     */
    private function decodeCacheItem(string $contents): ?array
    {
        $stored = @unserialize($contents);
        if (!\is_array($stored)
            || !\array_key_exists(0, $stored)
            || !\array_key_exists(1, $stored)
            || !\array_key_exists(2, $stored)
            || !\is_array($stored[1])
            || (!\is_int($stored[2]) && null !== $stored[2])
        ) {
            return null;
        }

        $tags = [];
        foreach ($stored[1] as $tag) {
            if (!\is_string($tag)) {
                return null;
            }

            $tags[$tag] = $tag;
        }

        return [$stored[0], $tags, $stored[2]];
    }

    /**
     * @return list<string>
     */
    private function decodeList(string $contents): array
    {
        $stored = @unserialize($contents);
        if (!\is_array($stored)) {
            return [];
        }

        $list = [];
        foreach ($stored as $key) {
            if (!\is_string($key)) {
                return [];
            }

            $list[] = $key;
        }

        return $list;
    }
}
