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

use Cache\Adapter\Common\Exception\InvalidArgumentException;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CacheItem implements PhpCacheItem
{
    /** @var array<string, string> */
    private array $prevTags = [];

    /** @var array<string, string> */
    private array $tags = [];

    /**
     * @var (\Closure(): array{0: bool, 1: mixed, 2?: array<string, string>, 3?: int|null})|null
     */
    private ?\Closure $callable = null;

    private string $key;

    private mixed $value = null;

    /**
     * The expiration timestamp is the source of truth. This is the UTC timestamp
     * when the cache item expire. A value of zero means it never expires. A nullvalue
     * means that no expiration is set.
     */
    private ?int $expirationTimestamp = null;

    private bool $hasValue = false;

    /**
     * @param (\Closure(): array{0: bool, 1: mixed, 2?: array<string, string>, 3?: int|null})|bool|null $callable or boolean hasValue
     */
    public function __construct(string $key, \Closure|bool|null $callable = null, mixed $value = null)
    {
        $this->key = $key;

        if (true === $callable) {
            $this->hasValue = true;
            $this->value = $value;
        } elseif (false !== $callable) {
            // This must be a callable or null
            $this->callable = $callable;
        }
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hasValue = true;
        $this->callable = null;

        return $this;
    }

    public function get(): mixed
    {
        if (!$this->isHit()) {
            return null;
        }

        return $this->value;
    }

    public function isHit(): bool
    {
        $this->initialize();

        if (!$this->hasValue) {
            return false;
        }

        if (null !== $this->expirationTimestamp) {
            return $this->expirationTimestamp > time();
        }

        return true;
    }

    public function getExpirationTimestamp(): ?int
    {
        $this->initialize();

        return $this->expirationTimestamp;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        if (null !== $expiration) {
            $this->expirationTimestamp = $expiration->getTimestamp();
        } else {
            $this->expirationTimestamp = null;
        }

        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        if (null === $time) {
            $this->expirationTimestamp = null;
        } elseif ($time instanceof \DateInterval) {
            $date = new \DateTime();
            $date->add($time);
            $this->expirationTimestamp = $date->getTimestamp();
        } else {
            $now = time();
            if ($time > PHP_INT_MAX - $now) {
                $this->expirationTimestamp = PHP_INT_MAX;
            } elseif ($time < PHP_INT_MIN + $now) {
                $this->expirationTimestamp = PHP_INT_MIN;
            } else {
                $this->expirationTimestamp = $now + $time;
            }
        }

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
        $this->tags = [];
        $this->tag($tags);

        return $this;
    }

    /**
     * Adds a tag to a cache item.
     *
     * @param string|array<array-key, mixed> $tags A tag or array of tags
     *
     * @throws InvalidArgumentException when $tag is not valid
     */
    private function tag(string|array $tags): static
    {
        $this->initialize();

        if (!is_array($tags)) {
            $tags = [$tags];
        }
        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                throw new InvalidArgumentException(sprintf('Cache tag must be string, "%s" given', is_object($tag) ? get_class($tag) : gettype($tag)));
            }
            if (isset($this->tags[$tag])) {
                continue;
            }
            if (!isset($tag[0])) {
                throw new InvalidArgumentException('Cache tag length must be greater than zero');
            }
            if (isset($tag[strcspn($tag, '{}()/\@:')])) {
                throw new InvalidArgumentException(sprintf('Cache tag "%s" contains reserved characters {}()/\@:', $tag));
            }
            $this->tags[$tag] = $tag;
        }

        return $this;
    }

    /**
     * If callable is not null, execute it an populate this object with values.
     */
    private function initialize(): void
    {
        if (null !== $this->callable) {
            // $func will be $adapter->fetchObjectFromCache();
            $func = $this->callable;
            $result = $func();
            $this->hasValue = $result[0];
            $this->value = $result[1];
            $this->prevTags = $result[2] ?? [];
            $this->expirationTimestamp = $result[3] ?? null;

            $this->callable = null;
        }
    }

    /**
     * @internal This function should never be used and considered private.
     *
     * Move tags from $tags to $prevTags
     */
    public function moveTagsToPrevious(): void
    {
        $this->prevTags = $this->tags;
        $this->tags = [];
    }
}
