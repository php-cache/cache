<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Prefixed;

use Psr\Cache\CacheItemInterface;

/** @internal */
class PrefixedCacheItem implements CacheItemInterface
{
    public function __construct(
        private readonly string $key,
        private readonly CacheItemInterface $item,
        private readonly object $owner,
    ) {
    }

    public function unwrap(): CacheItemInterface
    {
        return $this->item;
    }

    public function isOwnedBy(object $owner): bool
    {
        return $this->owner === $owner;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->item->get();
    }

    public function isHit(): bool
    {
        return $this->item->isHit();
    }

    public function set(mixed $value): static
    {
        $this->item->set($value);

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        $this->item->expiresAt($expiration);

        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        $this->item->expiresAfter($time);

        return $this;
    }
}
