<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Taggable;

use Cache\Taggable\Exception\InvalidArgumentException;
use Cache\TagInterop\TaggableCacheItemInterface;
use Cache\TagInterop\TaggableCacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * This adapter lets you make any PSR-6 cache pool taggable. If a pool is
 * already taggable, it is simply returned by makeTaggable. Tags are stored
 * either in the same cache pool, or a a separate pool, and both of these
 * appoaches come with different caveats.
 *
 * A general caveat is that using this adapter reserves any cache key starting
 * with '__tag.'.
 *
 * Using the same pool is precarious if your cache does LRU evictions of items
 * even if they do not expire (as in e.g. memcached). If so, the tag item may
 * be evicted without all of the tagged items having been evicted first,
 * causing items to lose their tags.
 *
 * In order to mitigate this issue, you may use a separate, more persistent
 * pool for your tag items. Do however note that if you are doing so, the
 * entire pool is reserved for tags, as this pool is cleared whenever the
 * main pool is cleared.
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 *
 * @phpstan-consistent-constructor
 */
class TaggablePSR6PoolAdapter implements TaggableCacheItemPoolInterface
{
    private CacheItemPoolInterface $cachePool;

    private CacheItemPoolInterface $tagStorePool;

    private readonly object $owner;

    protected function __construct(CacheItemPoolInterface $cachePool, ?CacheItemPoolInterface $tagStorePool = null)
    {
        $this->cachePool = $cachePool;
        $this->owner = new \stdClass();
        if ($tagStorePool) {
            $this->tagStorePool = $tagStorePool;
        } else {
            $this->tagStorePool = $cachePool;
        }
    }

    /**
     * @param CacheItemPoolInterface      $cachePool    The pool to which to add tagging capabilities
     * @param CacheItemPoolInterface|null $tagStorePool The pool to store tags in. If null is passed, the main pool is used
     */
    public static function makeTaggable(CacheItemPoolInterface $cachePool, ?CacheItemPoolInterface $tagStorePool = null): TaggableCacheItemPoolInterface
    {
        if ($cachePool instanceof TaggableCacheItemPoolInterface && null === $tagStorePool) {
            return $cachePool;
        }

        return new static($cachePool, $tagStorePool);
    }

    public function getItem(string $key): TaggableCacheItemInterface
    {
        $this->validateKey($key);

        return TaggablePSR6ItemAdapter::makeTaggable($this->cachePool->getItem($key), $this->owner);
    }

    /**
     * @param array<array-key, string> $keys
     *
     * @return iterable<string, TaggableCacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        $keys = $this->validateKeys($keys);

        return $this->wrapItems($this->cachePool->getItems($keys));
    }

    /**
     * @param iterable<array-key, mixed> $items
     *
     * @return \Generator<string, TaggableCacheItemInterface>
     */
    private function wrapItems(iterable $items): \Generator
    {
        foreach ($items as $item) {
            if (!$item instanceof CacheItemInterface) {
                throw new \UnexpectedValueException('A cache pool returned an invalid cache item.');
            }

            yield $item->getKey() => TaggablePSR6ItemAdapter::makeTaggable($item, $this->owner);
        }
    }

    public function hasItem(string $key): bool
    {
        $this->validateKey($key);

        return $this->cachePool->hasItem($key);
    }

    public function clear(): bool
    {
        if (!$this->cachePool->clear()) {
            return false;
        }

        return $this->tagStorePool === $this->cachePool || $this->tagStorePool->clear();
    }

    public function deleteItem(string $key): bool
    {
        $this->validateKey($key);
        $item = $this->getItem($key);
        $tags = $this->getTagEntries($item);

        if (!$this->cachePool->deleteItem($key)) {
            return false;
        }

        return $this->removeTagEntries($item, $tags);
    }

    public function deleteItems(array $keys): bool
    {
        $keys = $this->validateKeys($keys);
        $entries = [];
        foreach ($keys as $key) {
            $item = $this->getItem($key);
            $entries[] = [$item, $this->getTagEntries($item)];
        }

        if (!$this->cachePool->deleteItems($keys)) {
            return false;
        }

        $tagsRemoved = true;
        foreach ($entries as [$item, $tags]) {
            $tagsRemoved = $this->removeTagEntries($item, $tags) && $tagsRemoved;
        }

        return $tagsRemoved;
    }

    private function validateKey(string $key): void
    {
        if ('' === $key) {
            throw new InvalidArgumentException('Cache key cannot be an empty string');
        }

        if (preg_match('|[\{\}\(\)/\\\@\:]|', $key)) {
            throw new InvalidArgumentException(sprintf('Invalid key: "%s". The key contains one or more characters reserved for future extension: {}()/\@:', $key));
        }
    }

    /**
     * @param array<array-key, mixed> $keys
     *
     * @return list<string>
     */
    private function validateKeys(array $keys): array
    {
        $validatedKeys = [];
        foreach ($keys as $key) {
            if (!is_string($key)) {
                throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given', get_debug_type($key)));
            }

            $this->validateKey($key);
            $validatedKeys[] = $key;
        }

        return $validatedKeys;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof TaggablePSR6ItemAdapter || !$item->isOwnedBy($this->owner)) {
            throw new InvalidArgumentException('Cache items are not transferable between pools. Item MUST implement TaggablePSR6ItemAdapter.');
        }

        $previousTags = $this->getTagEntries($item);

        if (!$this->cachePool->save($item->unwrap())) {
            return false;
        }

        $tagsRemoved = $this->removeTagEntries($item, $previousTags);
        if ($this->cachePool->hasItem($item->getKey())) {
            $tagsSaved = $this->saveTags($item);

            return $tagsSaved && $tagsRemoved;
        }

        return $tagsRemoved;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        $itemsSaved = $this->cachePool->commit();
        $tagsSaved = $this->tagStorePool === $this->cachePool || $this->tagStorePool->commit();

        return $tagsSaved && $itemsSaved;
    }

    protected function appendListItem(string $name, string $value): bool
    {
        $listItem = $this->tagStorePool->getItem($name);
        $list = $this->getList($name);
        $list[] = $value;
        $listItem->set($list);

        return $this->tagStorePool->save($listItem);
    }

    protected function removeList(string $name): bool
    {
        return $this->tagStorePool->deleteItem($name);
    }

    protected function removeListItem(string $name, string $key): bool
    {
        $listItem = $this->tagStorePool->getItem($name);
        $list = [];
        foreach ($this->getList($name) as $value) {
            if ($value !== $key) {
                $list[] = $value;
            }
        }

        $listItem->set($list);

        return $this->tagStorePool->save($listItem);
    }

    /**
     * @return list<string>
     */
    protected function getList(string $name): array
    {
        $listItem = $this->tagStorePool->getItem($name);
        $stored = $listItem->get();
        if (!is_array($stored)) {
            return [];
        }

        $list = [];
        foreach ($stored as $value) {
            if (is_string($value)) {
                $list[] = $value;
            }
        }

        return $list;
    }

    protected function getTagKey(string $tag): string
    {
        return '__tag.'.$tag;
    }

    /** Save the item's current tags in the tag store. */
    private function saveTags(TaggablePSR6ItemAdapter $item): bool
    {
        return $this->saveTagEntries($item->getKey(), $item->getTags());
    }

    /**
     * @param array<string, string> $tags
     */
    private function saveTagEntries(string $key, array $tags): bool
    {
        $saved = true;
        foreach ($tags as $tag) {
            $saved = $this->appendListItem($this->getTagKey($tag), $key) && $saved;
        }

        return $saved;
    }

    /**
     * @param array<array-key, string> $tags
     */
    public function invalidateTags(array $tags): bool
    {
        $invalidTags = array_fill_keys($tags, true);
        $itemIds = [];
        foreach ($tags as $tag) {
            foreach ($this->getList($this->getTagKey($tag)) as $itemId) {
                $item = $this->getItem($itemId);
                if (!$item->isHit()) {
                    continue;
                }

                foreach ($item->getPreviousTags() as $storedTag) {
                    if (isset($invalidTags[$storedTag])) {
                        $itemIds[$itemId] = $itemId;
                        break;
                    }
                }
            }
        }

        // Remove all items with the tag
        $success = $this->deleteItems(array_values($itemIds));

        if ($success) {
            // Remove the tag list
            foreach ($tags as $tag) {
                $success = $this->removeList($this->getTagKey($tag)) && $success;
            }
        }

        return $success;
    }

    public function invalidateTag(string $tag): bool
    {
        return $this->invalidateTags([$tag]);
    }

    /**
     * @return array<string, string>
     */
    private function getTagEntries(TaggableCacheItemInterface $item): array
    {
        $tags = $item->getPreviousTags();
        $stored = TaggablePSR6ItemAdapter::makeTaggable($this->cachePool->getItem($item->getKey()), $this->owner);
        foreach ($stored->getPreviousTags() as $tag) {
            $tags[$tag] = $tag;
        }

        return $tags;
    }

    /**
     * @param array<string, string> $tags
     */
    private function removeTagEntries(TaggableCacheItemInterface $item, array $tags): bool
    {
        $removed = true;
        foreach ($tags as $tag) {
            $removed = $this->removeListItem($this->getTagKey($tag), $item->getKey()) && $removed;
        }

        return $removed;
    }
}
