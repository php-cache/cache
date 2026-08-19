<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Common;

use Cache\Adapter\Common\Exception\CacheException;
use Cache\Adapter\Common\Exception\CachePoolException;
use Cache\Adapter\Common\Exception\InvalidArgumentException;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
abstract class AbstractCachePool implements PhpCachePool, LoggerAwareInterface, CacheInterface
{
    public const SEPARATOR_TAG = '!';

    private ?LoggerInterface $logger = null;

    /**
     * @var array<string, PhpCacheItem>
     */
    protected array $deferred = [];

    /**
     * @param int|null $ttl seconds from now
     *
     * @return bool true if saved
     */
    abstract protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool;

    /**
     * Fetch an object from the cache implementation.
     *
     * If it is a cache miss, it MUST return [false, null, [], null]
     *
     * @return array{0: bool, 1: mixed, 2: array<string, string>, 3: int|null}
     */
    abstract protected function fetchObjectFromCache(string $key): array;

    /**
     * Clear all objects from cache.
     *
     * @return bool false if error
     */
    abstract protected function clearAllObjectsFromCache(): bool;

    /**
     * Remove one object from cache.
     */
    abstract protected function clearOneObjectFromCache(string $key): bool;

    /**
     * Get an array with all the values in the list named $name.
     *
     * @return array<int, string>
     */
    abstract protected function getList(string $name): array;

    /**
     * Remove the list.
     */
    abstract protected function removeList(string $name): bool;

    /**
     * Add a item key on a list named $name.
     */
    abstract protected function appendListItem(string $name, string $key): bool;

    /**
     * Remove an item from the list.
     */
    abstract protected function removeListItem(string $name, string $key): bool;

    /**
     * Make sure to commit before we destruct.
     */
    public function __destruct()
    {
        $this->commit();
    }

    public function getItem(string $key): PhpCacheItem
    {
        $this->validateKey($key);
        if (isset($this->deferred[$key])) {
            /** @var CacheItem $item */
            $item = clone $this->deferred[$key];
            $item->moveTagsToPrevious();

            return $item;
        }

        $func = function () use ($key) {
            try {
                return $this->fetchObjectFromCache($key);
            } catch (\Exception $e) {
                $this->handleException($e, 'getItem');
            }
        };

        return new CacheItem($key, $func);
    }

    /**
     * @param array<array-key, string> $keys
     *
     * @return iterable<string, PhpCacheItem>
     */
    public function getItems(array $keys = []): iterable
    {
        $validatedKeys = [];
        foreach ($keys as $key) {
            $key = $this->validateKey($key);
            $validatedKeys["\0".$key] = $key;
        }

        return $this->generateItems(array_values($validatedKeys));
    }

    /**
     * @param list<string> $keys
     *
     * @return \Generator<string, PhpCacheItem>
     */
    private function generateItems(array $keys): \Generator
    {
        foreach ($keys as $key) {
            yield $key => $this->getItem($key);
        }
    }

    public function hasItem(string $key): bool
    {
        try {
            return $this->getItem($key)->isHit();
        } catch (\Exception $e) {
            $this->handleException($e, __FUNCTION__);
        }
    }

    public function clear(): bool
    {
        // Clear the deferred items
        $this->deferred = [];

        try {
            return $this->clearAllObjectsFromCache();
        } catch (\Exception $e) {
            $this->handleException($e, __FUNCTION__);
        }
    }

    public function deleteItem(string $key): bool
    {
        try {
            return $this->deleteItems([$key]);
        } catch (\Exception $e) {
            $this->handleException($e, __FUNCTION__);
        }
    }

    public function deleteItems(array $keys): bool
    {
        $validatedKeys = [];
        foreach ($keys as $key) {
            $validatedKeys[] = $this->validateKey($key);
        }

        foreach ($validatedKeys as $key) {
            unset($this->deferred[$key]);
        }

        $deleted = $this->commit();
        foreach ($validatedKeys as $key) {
            $item = $this->getItem($key);
            $tags = $this->getTagEntries($item);

            if (!$this->clearOneObjectFromCache($key)) {
                $deleted = false;
                continue;
            }

            if (!$this->removeTagEntries($item, $tags)) {
                $deleted = false;
            }
        }

        return $deleted;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof PhpCacheItem) {
            $e = new InvalidArgumentException('Cache items are not transferable between pools. Item MUST implement PhpCacheItem.');
            $this->handleException($e, __FUNCTION__);
        }

        $timeToLive = null;
        if (null !== $timestamp = $item->getExpirationTimestamp()) {
            $now = time();
            if ($timestamp <= $now) {
                return $this->deleteItem($item->getKey());
            }

            $timeToLive = $timestamp - $now;
        }

        try {
            $previousTags = $this->getTagEntries($item);
            if (!$this->storeItemInCache($item, $timeToLive)) {
                return false;
            }

            unset($this->deferred[$item->getKey()]);
            $tagsRemoved = $this->removeTagEntries($item, $previousTags);
            $tagsSaved = $this->saveTags($item);

            return $tagsSaved && $tagsRemoved;
        } catch (\Exception $e) {
            $this->handleException($e, __FUNCTION__);
        }
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$item instanceof PhpCacheItem) {
            $e = new InvalidArgumentException('Cache items are not transferable between pools. Item MUST implement PhpCacheItem.');
            $this->handleException($e, __FUNCTION__);
        }

        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    public function commit(): bool
    {
        $deferred = $this->deferred;
        $this->deferred = [];
        $pending = $deferred;

        $saved = true;
        foreach ($deferred as $key => $item) {
            try {
                if (!$this->save($item)) {
                    $saved = false;
                }
            } catch (\Throwable $e) {
                $this->deferred = array_replace($pending, $this->deferred);

                throw $e;
            }

            unset($pending[$key]);
        }

        return $saved;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function validateKey(mixed $key): string
    {
        if (!\is_string($key)) {
            $e = new InvalidArgumentException(\sprintf(
                'Cache key must be string, "%s" given',
                \gettype($key)
            ));
            $this->handleException($e, __FUNCTION__);
        }
        if (!isset($key[0])) {
            $e = new InvalidArgumentException('Cache key cannot be an empty string');
            $this->handleException($e, __FUNCTION__);
        }
        if (preg_match('|[\{\}\(\)/\\\@\:]|', $key)) {
            $e = new InvalidArgumentException(\sprintf(
                'Invalid key: "%s". The key contains one or more characters reserved for future extension: {}()/\@:',
                $key
            ));
            $this->handleException($e, __FUNCTION__);
        }

        return $key;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Logs with an arbitrary level if the logger exists.
     *
     * @param array<string, mixed> $context
     */
    protected function log(mixed $level, string $message, array $context = []): void
    {
        if (null !== $this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Log exception and rethrow it.
     *
     * @throws CachePoolException
     */
    private function handleException(\Exception $e, string $function): never
    {
        $level = 'alert';
        if ($e instanceof InvalidArgumentException) {
            $level = 'warning';
        }

        $this->log($level, $e->getMessage(), ['exception' => $e]);
        if (!$e instanceof CacheException) {
            $e = new CachePoolException(\sprintf('Exception thrown when executing "%s". ', $function), 0, $e);
        }

        throw $e;
    }

    /**
     * @param list<string> $tags
     */
    public function invalidateTags(array $tags): bool
    {
        $invalidTags = array_fill_keys($tags, true);
        $itemIds = [];
        foreach ($tags as $tag) {
            foreach ($this->getList($this->getTagKey($tag)) as $itemId) {
                $stored = $this->fetchObjectFromCache($itemId);
                foreach ($stored[2] as $storedTag) {
                    if ($stored[0] && isset($invalidTags[$storedTag])) {
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

    protected function saveTags(PhpCacheItem $item): bool
    {
        $saved = true;
        $tags = $item->getTags();
        foreach ($tags as $tag) {
            $saved = $this->appendListItem($this->getTagKey($tag), $item->getKey()) && $saved;
        }

        return $saved;
    }

    /**
     * Removes the key form all tag lists. When an item with tags is removed
     * we MUST remove the tags. If we fail to remove the tags a new item with
     * the same key will automatically get the previous tags.
     *
     * @return $this
     */
    protected function preRemoveItem(string $key): static
    {
        $item = $this->getItem($key);
        $this->removeTagEntries($item, $this->getTagEntries($item));

        return $this;
    }

    /**
     * @return array<string, string>
     */
    private function getTagEntries(PhpCacheItem $item): array
    {
        $stored = $this->fetchObjectFromCache($item->getKey());
        $tags = $item->getPreviousTags();
        foreach ($stored[2] as $tag) {
            $tags[$tag] = $tag;
        }

        return $tags;
    }

    /**
     * @param array<string, string> $tags
     */
    private function removeTagEntries(PhpCacheItem $item, array $tags): bool
    {
        $removed = true;
        foreach ($tags as $tag) {
            $removed = $this->removeListItem($this->getTagKey($tag), $item->getKey()) && $removed;
        }

        return $removed;
    }

    protected function getTagKey(string $tag): string
    {
        return 'tag'.self::SEPARATOR_TAG.$tag;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->getItem($key);
        if (!$item->isHit()) {
            return $default;
        }

        return $item->get();
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        $item = $this->getItem($key);
        $item->set($value);
        $item->expiresAfter($ttl);

        return $this->save($item);
    }

    public function delete(string $key): bool
    {
        return $this->deleteItem($key);
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        }

        $items = $this->getItems($keys);

        return $this->generateValues($default, $items);
    }

    /**
     * @param iterable<string, CacheItemInterface> $items
     *
     * @return \Generator<string, mixed, mixed, void>
     */
    private function generateValues(mixed $default, iterable $items): \Generator
    {
        foreach ($items as $key => $item) {
            /** @var CacheItemInterface $item */
            if (!$item->isHit()) {
                yield $key => $default;
            } else {
                yield $key => $item->get();
            }
        }
    }

    /**
     * @param iterable<string|int, mixed> $values
     */
    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        $keys = [];
        $arrayValues = [];
        foreach ($values as $key => $value) {
            if (\is_int($key)) {
                $key = (string) $key;
            }
            $this->validateKey($key);
            $keys[] = $key;
            $arrayValues[$key] = $value;
        }

        $items = $this->getItems($keys);
        $itemSuccess = true;
        foreach ($items as $key => $item) {
            $item->set($arrayValues[$key]);

            try {
                $item->expiresAfter($ttl);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
            }

            $itemSaved = $this->saveDeferred($item);
            $itemSuccess = $itemSaved && $itemSuccess;
        }

        $itemsCommitted = $this->commit();

        return $itemSuccess && $itemsCommitted;
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        }

        return $this->deleteItems($keys);
    }

    public function has(string $key): bool
    {
        return $this->hasItem($key);
    }
}
