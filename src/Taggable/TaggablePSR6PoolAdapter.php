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
 * Adds generation-based tags to any PSR-6 pool. Missing or changed tag
 * generations make the cached item a miss.
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 *
 * @phpstan-consistent-constructor
 */
class TaggablePSR6PoolAdapter implements TaggableCacheItemPoolInterface
{
    private const GENERATION_EXPIRATION = '@2147483647';

    private const TAG_KEY_PREFIX = '__tag.';

    protected readonly CacheItemPoolInterface $cachePool;

    protected readonly CacheItemPoolInterface $tagStorePool;

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

        return $this->wrapItem($this->cachePool->getItem($key));
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

            yield $item->getKey() => $this->wrapItem($item);
        }
    }

    private function wrapItem(CacheItemInterface $item): TaggablePSR6ItemAdapter
    {
        return TaggablePSR6ItemAdapter::makeTaggable(
            $item,
            $this->owner,
            fn (array $tags, array $generations): bool => $this->tagGenerationsAreCurrent($tags, $generations),
        );
    }

    public function hasItem(string $key): bool
    {
        $this->validateKey($key);

        return $this->getItem($key)->isHit();
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

        return $this->cachePool->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        $keys = $this->validateKeys($keys);

        return $this->cachePool->deleteItems($keys);
    }

    private function validateKey(string $key): void
    {
        if ('' === $key) {
            throw new InvalidArgumentException('Cache key cannot be an empty string');
        }

        if (preg_match('|[\{\}\(\)/\\\@\:]|', $key)) {
            throw new InvalidArgumentException(\sprintf('Invalid key: "%s". The key contains one or more characters reserved for future extension: {}()/\@:', $key));
        }
        $tagKeyPrefix = $this->tagKeyPrefix();
        if ($this->tagStorePool === $this->cachePool && str_starts_with($key, $tagKeyPrefix)) {
            throw new InvalidArgumentException(\sprintf('Invalid key: "%s". Cache keys starting with "%s" are reserved for tag metadata.', $key, $tagKeyPrefix));
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
            if (!\is_string($key)) {
                throw new InvalidArgumentException(\sprintf('Cache key must be string, "%s" given', get_debug_type($key)));
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

        if (!$item->isDirty()) {
            return $this->cachePool->save($item->unwrap());
        }

        $generations = $this->resolveTagGenerations($item->getTags());
        if (false === $generations) {
            return false;
        }
        $item->setTagGenerations($generations);

        return $this->cachePool->save($item->unwrap());
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

    protected function readTagGeneration(string $name): string|false|null
    {
        $generationItem = $this->tagStorePool->getItem($name);
        if (!$generationItem->isHit()) {
            return null;
        }

        $generation = $generationItem->get();

        return \is_string($generation) && '' !== $generation ? $generation : false;
    }

    protected function writeTagGeneration(string $name, string $generation): bool
    {
        $generationItem = $this->tagStorePool->getItem($name);
        $generationItem->set($generation);
        $generationItem->expiresAt(new \DateTimeImmutable(self::GENERATION_EXPIRATION));

        return $this->tagStorePool->save($generationItem);
    }

    protected function deleteTagGeneration(string $name): bool
    {
        return $this->tagStorePool->deleteItem($name);
    }

    protected function getTagKey(string $tag): string
    {
        $prefix = $this->tagKeyPrefix();

        return $prefix.substr(hash('sha256', $tag), 0, 64 - \strlen($prefix));
    }

    protected function getTagKeyPrefix(): string
    {
        return self::TAG_KEY_PREFIX;
    }

    /** @return non-empty-string */
    private function tagKeyPrefix(): string
    {
        $prefix = $this->getTagKeyPrefix();
        if ('' === $prefix || 32 < \strlen($prefix) || 1 === preg_match('/[^A-Za-z0-9_.]/', $prefix)) {
            throw new \LogicException('The tag metadata key prefix must use 1 to 32 portable PSR-6 key characters.');
        }

        return $prefix;
    }

    /**
     * @param array<string, string> $tags
     *
     * @return list<string>|false
     */
    private function resolveTagGenerations(array $tags): array|false
    {
        $generations = [];
        foreach ($tags as $tag) {
            $name = $this->getTagKey($tag);
            $generation = $this->readTagGeneration($name);
            if (false === $generation) {
                return false;
            }
            if (null === $generation) {
                $generation = bin2hex(random_bytes(16));
                if (!$this->writeTagGeneration($name, $generation)) {
                    return false;
                }

                $generation = $this->readTagGeneration($name);
                if (!\is_string($generation)) {
                    return false;
                }
            }

            $generations[] = $generation;
        }

        return $generations;
    }

    /**
     * @param array<array-key, string> $tags
     */
    public function invalidateTags(array $tags): bool
    {
        $validatedTags = [];
        foreach ($tags as $tag) {
            $validatedTags[] = TaggablePSR6ItemAdapter::validateTag($tag);
        }

        $success = true;
        foreach ($validatedTags as $tag) {
            $success = $this->deleteTagGeneration($this->getTagKey($tag)) && $success;
        }

        return $success;
    }

    public function invalidateTag(string $tag): bool
    {
        return $this->invalidateTags([$tag]);
    }

    /**
     * @param list<string> $tags
     * @param list<string> $generations
     */
    private function tagGenerationsAreCurrent(array $tags, array $generations): bool
    {
        foreach ($tags as $index => $tag) {
            $generation = $generations[$index] ?? null;
            if ($generation !== $this->readTagGeneration($this->getTagKey($tag))) {
                return false;
            }
        }

        return true;
    }
}
