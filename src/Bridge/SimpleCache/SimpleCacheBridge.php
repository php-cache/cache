<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Bridge\SimpleCache;

use Cache\Bridge\SimpleCache\Exception\InvalidArgumentException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

/**
 * Adds a SimpleCache interface on a PSR-6 cache pool.
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 */
class SimpleCacheBridge implements CacheInterface
{
    protected CacheItemPoolInterface $cacheItemPool;

    /**
     * SimpleCacheBridge constructor.
     */
    public function __construct(CacheItemPoolInterface $cacheItemPool)
    {
        $this->cacheItemPool = $cacheItemPool;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);

        try {
            $item = $this->cacheItemPool->getItem($key);
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        if (!$item->isHit()) {
            return $default;
        }

        return $item->get();
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        $this->validateKey($key);

        try {
            $item = $this->cacheItemPool->getItem($key);
            $item->expiresAfter($ttl);
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        $item->set($value);

        return $this->cacheItemPool->save($item);
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);

        try {
            return $this->cacheItemPool->deleteItem($key);
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function clear(): bool
    {
        return $this->cacheItemPool->clear();
    }

    /**
     * @param iterable<mixed> $keys
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $keys = $this->prepareKeys($keys);

        try {
            $items = $this->cacheItemPool->getItems($keys);
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->generateValues($default, $items);
    }

    /**
     * @param iterable<array-key, CacheItemInterface> $items
     *
     * @return \Generator<string, mixed, mixed, void>
     */
    private function generateValues(mixed $default, iterable $items): \Generator
    {
        try {
            foreach ($items as $item) {
                $key = $item->getKey();
                if (!$item->isHit()) {
                    yield $key => $default;
                } else {
                    yield $key => $item->get();
                }
            }
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param iterable<mixed, mixed> $values
     */
    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        $keys = [];
        $arrayValues = [];
        foreach ($values as $key => $value) {
            if (!is_string($key) && !is_int($key)) {
                throw new InvalidArgumentException(sprintf('Cache key must be string or integer, "%s" given', get_debug_type($key)));
            }

            if (is_int($key)) {
                $key = (string) $key;
            }

            $this->validateKey($key);

            $keys[] = $key;
            $arrayValues[$key] = $value;
        }

        try {
            $items = $this->cacheItemPool->getItems($keys);
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        try {
            $preparedItems = [];
            foreach ($items as $item) {
                $preparedItems[] = $item;
            }

            $itemSuccess = true;
            foreach ($preparedItems as $item) {
                $key = $item->getKey();
                $item->set($arrayValues[$key]);
                $item->expiresAfter($ttl);
                $itemSaved = $this->cacheItemPool->saveDeferred($item);
                $itemSuccess = $itemSaved && $itemSuccess;
            }

            $itemsCommitted = $this->cacheItemPool->commit();

            return $itemSuccess && $itemsCommitted;
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param iterable<mixed> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $keys = $this->prepareKeys($keys);

        try {
            return $this->cacheItemPool->deleteItems($keys);
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function has(string $key): bool
    {
        $this->validateKey($key);

        try {
            return $this->cacheItemPool->hasItem($key);
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param iterable<mixed> $keys
     *
     * @return list<string>
     */
    private function prepareKeys(iterable $keys): array
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

    private function validateKey(string $key): void
    {
        if ('' === $key) {
            throw new InvalidArgumentException('Cache key cannot be an empty string');
        }

        if (preg_match('|[\{\}\(\)/\\\@\:]|', $key)) {
            throw new InvalidArgumentException(sprintf('Invalid key: "%s". The key contains one or more characters reserved for future extension: {}()/\@:', $key));
        }
    }
}
