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
use Cache\Taggable\TaggableItemInterface;
use Cache\Taggable\TaggablePoolInterface;
use Cache\Taggable\TaggablePoolTrait;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use Psr\Cache\CacheItemInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class FilesystemCachePool extends AbstractCachePool implements TaggablePoolInterface
{
    use TaggablePoolTrait;

    /**
     * @type Filesystem
     */
    private $filesystem;

    /**
     * The folder should not begin nor end with a slash. Example: path/to/cache.
     *
     * @type string
     */
    private $folder;

    /**
     * @param Filesystem $filesystem
     * @param string     $folder
     */
    public function __construct(Filesystem $filesystem, $folder = 'cache')
    {
        $this->folder = $folder;

        $this->filesystem = $filesystem;
        $this->filesystem->createDir($this->folder);
    }

    /**
     * @param string $folder
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        $file = $this->getFilePath($key);
        if (!$this->filesystem->has($file)) {
            return [false, null, []];
        }

        $data = unserialize($this->filesystem->read($file));
        if ($data[0] !== null && time() > $data[0]) {
            foreach ($data[2] as $tag) {
                $this->removeListItem($this->getTagKey($tag), $key);
            }
            $this->forceClear($key);

            return [false, null, []];
        }

        return [true, $data[1], $data[2]];
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllObjectsFromCache()
    {
        $this->filesystem->deleteDir($this->folder);
        $this->filesystem->createDir($this->folder);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key)
    {
        $this->preRemoveItem($key);

        return $this->forceClear($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache(CacheItemInterface $item, $ttl)
    {
        $file = $this->getFilePath($item->getKey());
        if ($this->filesystem->has($file)) {
            $this->filesystem->delete($file);
        }

        $tags = [];
        if ($item instanceof TaggableItemInterface) {
            $tags = $item->getTags();
        }

        return $this->filesystem->write($file, serialize([
            ($ttl === null ? null : time() + $ttl),
            $item->get(),
            $tags,
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

        return sprintf('%s/%s', $this->folder, $key);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        if ($item instanceof TaggableItemInterface) {
            $this->saveTags($item);
        }

        return parent::save($item);
    }

    /**
     * {@inheritdoc}
     */
    protected function getList($name)
    {
        $file = $this->getFilePath($name);

        if (!$this->filesystem->has($file)) {
            $this->filesystem->write($file, serialize([]));
        }

        return unserialize($this->filesystem->read($file));
    }

    /**
     * {@inheritdoc}
     */
    protected function removeList($name)
    {
        $file = $this->getFilePath($name);
        $this->filesystem->delete($file);
    }

    /**
     * {@inheritdoc}
     */
    protected function appendListItem($name, $key)
    {
        $list   = $this->getList($name);
        $list[] = $key;

        return $this->filesystem->update($this->getFilePath($name), serialize($list));
    }

    /**
     * {@inheritdoc}
     */
    protected function removeListItem($name, $key)
    {
        $list = $this->getList($name);
        foreach ($list as $i => $item) {
            if ($item === $key) {
                unset($list[$i]);
            }
        }

        return $this->filesystem->update($this->getFilePath($name), serialize($list));
    }

    /**
     * @param $key
     *
     * @return bool
     */
    private function forceClear($key)
    {
        try {
            return $this->filesystem->delete($this->getFilePath($key));
        } catch (FileNotFoundException $e) {
            return true;
        }
    }
}
