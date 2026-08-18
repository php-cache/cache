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

use Cache\Prefixed\Exception\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

/**
 * PrefixedSimpleCache.
 *
 * Prefixes all cache keys with a string.
 *
 * @author ndobromirov
 */
class PrefixedSimpleCache implements CacheInterface
{
    use PrefixedUtilityTrait;

    private CacheInterface $cache;

    public function __construct(CacheInterface $simpleCache, string $prefix)
    {
        $this->cache = $simpleCache;
        $this->prefix = $this->encodePrefix($prefix);
    }

    public function clear(): bool
    {
        return $this->cache->clear();
    }

    public function delete(string $key): bool
    {
        $this->prefixValue($key);

        return $this->cache->delete($key);
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        }

        $keys = $this->prefixValues($keys);

        return $this->cache->deleteMultiple($keys);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->prefixValue($key);

        return $this->cache->get($key, $default);
    }

    /**
     * @param iterable<string> $keys
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        }

        $oldKeys = $keys;
        $keys = $this->prefixValues($keys);
        $keysMap = [];
        foreach ($keys as $index => $key) {
            $keysMap["\0".$key] = $oldKeys[$index];
        }

        $data = $this->cache->getMultiple($keys, $default);

        return $this->mapValues($data, $keysMap);
    }

    /**
     * @param iterable<array-key, mixed> $data
     * @param array<string, string>      $keysMap
     *
     * @return \Generator<string, mixed>
     */
    private function mapValues(iterable $data, array $keysMap): \Generator
    {
        foreach ($data as $key => $value) {
            $mappedKey = "\0".(string) $key;
            if (!\array_key_exists($mappedKey, $keysMap)) {
                continue;
            }

            yield $keysMap[$mappedKey] => $value;
        }
    }

    public function has(string $key): bool
    {
        $this->prefixValue($key);

        return $this->cache->has($key);
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        $this->prefixValue($key);

        return $this->cache->set($key, $value, $ttl);
    }

    /**
     * @param iterable<mixed, mixed> $values
     */
    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        $prefixedValues = [];
        foreach ($values as $key => $value) {
            if (!\is_string($key) && !\is_int($key)) {
                throw new InvalidArgumentException(\sprintf('Cache key must be string or integer, "%s" given', get_debug_type($key)));
            }

            $key = (string) $key;
            $this->prefixValue($key);
            $prefixedValues[] = [$key, $value];
        }

        return $this->cache->setMultiple($this->generateValues($prefixedValues), $ttl);
    }

    /**
     * @param list<array{string, mixed}> $values
     *
     * @return \Generator<string, mixed>
     */
    private function generateValues(array $values): \Generator
    {
        foreach ($values as [$key, $value]) {
            yield $key => $value;
        }
    }
}
