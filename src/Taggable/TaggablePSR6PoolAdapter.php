<?php

namespace Cache\Taggable;

use Cache\Taggable\TaggableItemInterface;
use Cache\Taggable\TaggablePoolInterface;
use Cache\Taggable\TaggablePoolTrait;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
* 
*/
class TaggablePSR6PoolAdapter implements TaggablePoolInterface
{
    use TaggablePoolTrait;

    private $cachePool;
    private $tagStorePool;

    private function __construct(CacheItemPoolInterface $cachePool, CacheItemPoolInterface $tagStorePool = null)
    {
        $this->cachePool = $cachePool;
        if ($tagStorePool) {
            $this->tagStorePool = $tagStorePool;
        } else {
            $this->tagStorePool = $cachePool;
        }
    }

    public static function makeTaggable(CacheItemPoolInterface $cachePool, CacheItemPoolInterface $tagStorePool = null) // @TODO naming?
    {
        if ($cachePool instanceOf TaggablePoolInterface && $tagStorePool === null) {
            return $cachePool;
        }

        return new self($cachePool, $tagStorePool);
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        return TaggablePSR6ItemAdapter::makeTaggable($this->cachePool->getItem($key));
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = array())
    {
        $items = $this->cachePool->getItems($keys);

        $wrappedItems = [];
        foreach ($items as $key => $item) {
            $wrappedItems[$key] = TaggablePSR6ItemAdapter::makeTaggable($item);
        }

        return $wrappedItems;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        return $this->cachePool->hasItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $ret = $this->cachePool->clear();
        return $this->tagStorePool->clear() && $ret; // Is this acceptable?
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        $this->preRemoveItem($key);
        return $this->cachePool->deleteItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        foreach ($keys as $key) {
            $this->preRemoveItem($key);
        }

        return $this->cachePool->deleteItems($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        $this->saveTags($item);
        return $this->cachePool->save($item->unwrap());
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        $this->saveTags($item);
        return $this->cachePool->saveDeferred($item->unwrap());
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $this->tagStorePool->commit();
        $this->cachePool->commit();
    }

    /**
     * {@inheritdoc}
     */
    protected function appendListItem($name, $value)
    {
        $listItem = $this->tagStorePool->getItem($name);
        if (!is_array($list = $listItem->get())) {
            $list = [];
        }

        $list[] = $value;
        $listItem->set($list);
        $this->tagStorePool->save($listItem);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeList($name)
    {
        return $this->tagStorePool->deleteItem($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeListItem($name, $key)
    {
        $listItem = $this->tagStorePool->getItem($name);
        if (!is_array($list = $listItem->get())) {
            $list = [];
        }

        $list = array_filter($list, function ($value) use ($key) { return $value !== $key; });

        $listItem->set($list);
        $this->tagStorePool->save($listItem);
    }

    /**
     * {@inheritdoc}
     */
    protected function getList($name)
    {
        $listItem = $this->tagStorePool->getItem($name);
        if (!is_array($list = $listItem->get())) {
            $list = [];
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     */
    private function getTagKey($tag)
    {
        return '__tag.'.$tag;
    }
}
