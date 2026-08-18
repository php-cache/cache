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
use Psr\Cache\CacheItemInterface;

/**
 * @internal
 *
 * An adapter for non-taggable cache items, to be used with the cache pool
 * adapter.
 *
 * This adapter stores tags along with the cached value, by storing wrapping
 * the item in an array structure containing both
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 */
class TaggablePSR6ItemAdapter implements TaggableCacheItemInterface
{
    private bool $initialized = false;

    private CacheItemInterface $cacheItem;

    /** @var array<string, string> */
    private array $prevTags = [];

    /** @var array<string, string> */
    private array $tags = [];

    private function __construct(CacheItemInterface $cacheItem, private readonly object $owner)
    {
        $this->cacheItem = $cacheItem;
    }

    public static function makeTaggable(CacheItemInterface $cacheItem, ?object $owner = null): self
    {
        return new self($cacheItem, $owner ?? new \stdClass());
    }

    public function unwrap(): CacheItemInterface
    {
        return $this->cacheItem;
    }

    public function isOwnedBy(object $owner): bool
    {
        return $this->owner === $owner;
    }

    public function getKey(): string
    {
        return $this->cacheItem->getKey();
    }

    public function get(): mixed
    {
        $rawItem = $this->cacheItem->get();
        $item = $this->unpackStoredItem($rawItem);

        if (null !== $item) {
            return $item['value'];
        }

        // This is an item stored before we used this fake cache
        return $rawItem;
    }

    public function isHit(): bool
    {
        return $this->cacheItem->isHit();
    }

    public function set(mixed $value): static
    {
        $this->initializeTags();

        $this->cacheItem->set([
            'value' => $value,
            'tags' => $this->tags,
        ]);

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getPreviousTags(): array
    {
        $this->initializeTags();

        return $this->prevTags;
    }

    /**
     * @return array<string, string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): static
    {
        $this->tags = [];

        return $this->tag($tags);
    }

    /**
     * @param string|array<array-key, mixed> $tags
     */
    private function tag(string|array $tags): static
    {
        if (!\is_array($tags)) {
            $tags = [$tags];
        }

        $this->initializeTags();

        foreach ($tags as $tag) {
            if (!\is_string($tag)) {
                throw new InvalidArgumentException(\sprintf('Cache tag must be string, "%s" given', \is_object($tag) ? $tag::class : \gettype($tag)));
            }
            if (isset($this->tags[$tag])) {
                continue;
            }
            if (!isset($tag[0])) {
                throw new InvalidArgumentException('Cache tag length must be greater than zero');
            }
            if (isset($tag[strcspn($tag, '{}()/\@:')])) {
                throw new InvalidArgumentException(\sprintf('Cache tag "%s" contains reserved characters {}()/\@:', $tag));
            }
            $this->tags[$tag] = $tag;
        }

        $this->updateTags();

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        $this->cacheItem->expiresAt($expiration);

        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        $this->cacheItem->expiresAfter($time);

        return $this;
    }

    private function updateTags(): void
    {
        $this->cacheItem->set([
            'value' => $this->get(),
            'tags' => $this->tags,
        ]);
    }

    private function initializeTags(): void
    {
        if (!$this->initialized) {
            if ($this->cacheItem->isHit()) {
                $rawItem = $this->cacheItem->get();
                $item = $this->unpackStoredItem($rawItem);

                if (null !== $item) {
                    $this->prevTags = $item['tags'];
                }
            }

            $this->initialized = true;
        }
    }

    /**
     * Read the value and tags from an item created by this class.
     *
     * @return array{value: mixed, tags: array<string, string>}|null
     */
    private function unpackStoredItem(mixed $rawItem): ?array
    {
        if (!\is_array($rawItem)
            || !\array_key_exists('value', $rawItem)
            || !isset($rawItem['tags'])
            || !\is_array($rawItem['tags'])
            || 2 !== \count($rawItem)
        ) {
            return null;
        }

        $tags = [];
        foreach ($rawItem['tags'] as $tag) {
            if (!\is_string($tag)) {
                return null;
            }

            $tags[$tag] = $tag;
        }

        return ['value' => $rawItem['value'], 'tags' => $tags];
    }
}
