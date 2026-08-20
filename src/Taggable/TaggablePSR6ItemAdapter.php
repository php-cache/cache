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
 * Wraps a PSR-6 item with its tag names and generation snapshots.
 *
 * @internal
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 */
class TaggablePSR6ItemAdapter implements TaggableCacheItemInterface
{
    private bool $dirty = false;

    /** @var \Closure(list<string>, list<string>): bool */
    private readonly \Closure $generationsAreCurrent;

    private bool $hasValue = false;

    private ?bool $hit = null;

    private bool $initialized = false;

    private CacheItemInterface $cacheItem;

    private bool $invalidStoredItem = false;

    /** @var array<string, string> */
    private array $prevTags = [];

    /** @var list<string> */
    private array $previousTagGenerations = [];

    /** @var array<string, string> */
    private array $tags = [];

    /** @var list<string> */
    private array $tagGenerations = [];

    private mixed $value = null;

    private bool $valueWasSet = false;

    /** @param (\Closure(list<string>, list<string>): bool)|null $generationsAreCurrent */
    private function __construct(CacheItemInterface $cacheItem, private readonly object $owner, ?\Closure $generationsAreCurrent)
    {
        $this->cacheItem = $cacheItem;
        $this->generationsAreCurrent = $generationsAreCurrent ?? static fn (array $tags, array $generations): bool => true;
    }

    /** @param (\Closure(list<string>, list<string>): bool)|null $generationsAreCurrent */
    public static function makeTaggable(CacheItemInterface $cacheItem, ?object $owner = null, ?\Closure $generationsAreCurrent = null): self
    {
        return new self($cacheItem, $owner ?? new \stdClass(), $generationsAreCurrent);
    }

    public function unwrap(): CacheItemInterface
    {
        return $this->cacheItem;
    }

    public function isOwnedBy(object $owner): bool
    {
        return $this->owner === $owner;
    }

    public function isDirty(): bool
    {
        return $this->dirty;
    }

    public function getKey(): string
    {
        return $this->cacheItem->getKey();
    }

    public function get(): mixed
    {
        $this->initialize();

        if (!$this->dirty && !$this->isHit()) {
            return null;
        }

        return $this->hasValue ? $this->value : null;
    }

    public function isHit(): bool
    {
        if (null !== $this->hit) {
            return $this->hit;
        }

        $this->initialize();
        if (!$this->cacheItem->isHit() || $this->invalidStoredItem) {
            return $this->hit = false;
        }
        if ([] === $this->prevTags) {
            return $this->hit = true;
        }
        $previousTags = array_values($this->prevTags);
        if (\count($previousTags) !== \count($this->previousTagGenerations)) {
            return $this->hit = false;
        }

        return $this->hit = ($this->generationsAreCurrent)($previousTags, $this->previousTagGenerations);
    }

    public function set(mixed $value): static
    {
        $this->initialize();
        $this->value = $value;
        $this->hasValue = true;
        $this->valueWasSet = true;
        $this->updateStoredItem();

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getPreviousTags(): array
    {
        $this->initialize();

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
        $this->initialize();
        if (!$this->valueWasSet && !$this->isHit()) {
            $this->value = null;
            $this->hasValue = true;
        }

        $this->tags = [];
        $this->tagGenerations = [];

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

        $this->initialize();

        foreach ($tags as $tag) {
            $tag = self::validateTag($tag);
            $tagIndex = self::tagIndex($tag);
            if (isset($this->tags[$tagIndex])) {
                continue;
            }
            $this->tags[$tagIndex] = $tag;
        }

        $this->updateStoredItem();

        return $this;
    }

    public static function validateTag(mixed $tag): string
    {
        if (!\is_string($tag)) {
            throw new InvalidArgumentException(\sprintf('Cache tag must be string, "%s" given', \is_object($tag) ? $tag::class : \gettype($tag)));
        }
        if (!isset($tag[0])) {
            throw new InvalidArgumentException('Cache tag length must be greater than zero');
        }
        if (isset($tag[strcspn($tag, '{}()/\@:')])) {
            throw new InvalidArgumentException(\sprintf('Cache tag "%s" contains reserved characters {}()/\@:', $tag));
        }

        return $tag;
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

    /** @param list<string> $generations */
    public function setTagGenerations(array $generations): void
    {
        if (\count($this->tags) !== \count($generations)) {
            throw new \LogicException('Tag generations must match the current tags.');
        }

        $this->tagGenerations = $generations;
        $this->updateStoredItem();
    }

    private function updateStoredItem(): void
    {
        $this->dirty = true;
        $this->cacheItem->set([
            'value' => $this->value,
            'tags' => array_values($this->tags),
            'tag_generations' => $this->tagGenerations,
        ]);
    }

    private function initialize(): void
    {
        if (!$this->initialized) {
            if ($this->cacheItem->isHit()) {
                $rawItem = $this->cacheItem->get();
                $item = $this->unpackStoredItem($rawItem);

                if (null !== $item) {
                    $this->value = $item['value'];
                    $this->hasValue = true;
                    $this->prevTags = $item['tags'];
                    $this->previousTagGenerations = $item['tag_generations'];
                } elseif (\is_array($rawItem) && \array_key_exists('tag_generations', $rawItem)) {
                    $this->invalidStoredItem = true;
                } else {
                    $this->value = $rawItem;
                    $this->hasValue = true;
                }
            }

            $this->initialized = true;
        }
    }

    /**
     * Read the value and tags from an item created by this class.
     *
     * @return array{value: mixed, tags: array<string, string>, tag_generations: list<string>}|null
     */
    private function unpackStoredItem(mixed $rawItem): ?array
    {
        if (!\is_array($rawItem)
            || !\array_key_exists('value', $rawItem)
            || !isset($rawItem['tags'])
            || !\is_array($rawItem['tags'])
            || (2 !== \count($rawItem) && 3 !== \count($rawItem))
        ) {
            return null;
        }

        $tags = [];
        foreach ($rawItem['tags'] as $tag) {
            if (!\is_string($tag)) {
                return null;
            }

            $tags[self::tagIndex($tag)] = $tag;
        }

        if (2 === \count($rawItem)) {
            return ['value' => $rawItem['value'], 'tags' => $tags, 'tag_generations' => []];
        }
        if (!isset($rawItem['tag_generations']) || !\is_array($rawItem['tag_generations']) || !array_is_list($rawItem['tag_generations'])) {
            return null;
        }

        $tagGenerations = [];
        foreach ($rawItem['tag_generations'] as $generation) {
            if (!\is_string($generation) || '' === $generation) {
                return null;
            }

            $tagGenerations[] = $generation;
        }
        if (\count($tags) !== \count($tagGenerations)) {
            return null;
        }

        return ['value' => $rawItem['value'], 'tags' => $tags, 'tag_generations' => $tagGenerations];
    }

    private static function tagIndex(string $tag): string
    {
        if (1 === preg_match('/^(?:0|-?[1-9][0-9]*)$/D', $tag) && (string) (int) $tag === $tag) {
            return ':'.$tag;
        }

        return $tag;
    }
}
