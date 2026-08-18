<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Namespaced;

use Cache\Namespaced\Exception\InvalidArgumentException;

/** @internal */
final class NamespacedTagMapper
{
    /** @var non-empty-string */
    private readonly string $prefix;

    public function __construct(string $namespace)
    {
        $this->prefix = 'ns.t.'.sha1($namespace).'.';
    }

    public function map(string $tag): string
    {
        return $this->prefix.$this->validateTag($tag);
    }

    /**
     * @param array<array-key, mixed> $tags
     *
     * @return array<array-key, string>
     */
    public function mapTags(array $tags): array
    {
        foreach ($tags as $index => $tag) {
            $tags[$index] = $this->prefix.$this->validateTag($tag);
        }

        return $tags;
    }

    /**
     * @param array<string, string> $tags
     *
     * @return array<string, string>
     */
    public function unmapTags(array $tags): array
    {
        $publicTags = [];
        foreach ($tags as $tag) {
            $publicTag = '' !== $tag && str_starts_with($tag, $this->prefix) ? substr($tag, \strlen($this->prefix)) : $tag;
            $publicTags[$publicTag] = $publicTag;
        }

        return $publicTags;
    }

    private function validateTag(mixed $tag): string
    {
        if (!\is_string($tag)) {
            throw new InvalidArgumentException(\sprintf('Cache tag must be string, "%s" given', get_debug_type($tag)));
        }

        if ('' === $tag) {
            throw new InvalidArgumentException('Cache tag length must be greater than zero');
        }

        if (isset($tag[strcspn($tag, '{}()/\\@:')])) {
            throw new InvalidArgumentException(\sprintf('Cache tag "%s" contains reserved characters {}()/\@:', $tag));
        }

        return $tag;
    }
}
