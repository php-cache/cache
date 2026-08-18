<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Chain;

use Cache\Adapter\Chain\Exception\NoPoolAvailableException;
use Cache\Adapter\Common\CacheItem;
use Cache\Adapter\Common\Exception\InvalidArgumentException;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Adapter\Common\PhpCachePool;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * `skip_on_failure` removes a pool after a backend exception, then the operation continues.
 * invalid cache keys always throw and never remove a pool.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CachePoolChain implements PhpCachePool, CacheInterface, LoggerAwareInterface
{
    private ?LoggerInterface $logger = null;

    /**
     * @var array<array-key, PhpCachePool>
     */
    private array $pools;

    /**
     * @var array{skip_on_failure: bool}
     */
    private array $options;

    /**
     * @param array<array-key, mixed>       $pools
     * @param array{skip_on_failure?: bool} $options
     */
    public function __construct(array $pools, array $options = [])
    {
        $validatedPools = [];
        foreach ($pools as $key => $pool) {
            if (!$pool instanceof PhpCachePool) {
                throw new \InvalidArgumentException('Every chain member must implement PhpCachePool.');
            }

            $validatedPools[$key] = $pool;
        }

        $this->pools = $validatedPools;

        if (!isset($options['skip_on_failure'])) {
            $options['skip_on_failure'] = false;
        }

        $this->options = $options;
    }

    public function getItem(string $key): PhpCacheItem
    {
        $result = null;
        $item = null;
        $needsSave = [];

        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $item = $pool->getItem($key);

                if ($item->isHit()) {
                    $result = $item;
                    break;
                }

                $needsSave[] = [$poolKey, $pool];
            } catch (\Exception $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }

        if (null !== $result) {
            foreach ($needsSave as [$poolKey, $pool]) {
                try {
                    $pool->save($this->createBackfillItem($result));
                } catch (\Exception $e) {
                    $this->handleException($poolKey, __FUNCTION__, $e);
                }
            }

            return $result;
        }

        if (null !== $item) {
            return $item;
        }

        throw new NoPoolAvailableException('No valid cache pool available for the chain.');
    }

    /**
     * @param list<string> $keys
     *
     * @return iterable<string, PhpCacheItem>
     */
    public function getItems(array $keys = []): iterable
    {
        $keys = $this->prepareKeys($keys);
        $hits = [];
        $loadedItems = [];
        $notFoundItems = [];
        $keysCount = \count($keys);
        $poolResponded = false;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $items = $pool->getItems($keys);
                $poolHits = [];
                $poolLoadedItems = [];
                $poolNotFoundItems = [];

                /** @var PhpCacheItem $item */
                foreach ($items as $item) {
                    $itemKey = $item->getKey();
                    $mappedKey = "\0".$itemKey;
                    if ($item->isHit()) {
                        if (!isset($hits[$mappedKey]) && !isset($poolHits[$mappedKey])) {
                            $poolHits[$mappedKey] = $item;
                            unset($poolNotFoundItems[$mappedKey]);
                        }
                    } elseif (!isset($hits[$mappedKey]) && !isset($poolHits[$mappedKey])) {
                        $poolNotFoundItems[$mappedKey] = $itemKey;
                    }
                    $poolLoadedItems[$mappedKey] = $item;
                }

                $poolResponded = true;
                foreach ($poolHits as $mappedKey => $item) {
                    $hits[$mappedKey] = $item;
                    $position = array_search($item->getKey(), $keys, true);
                    if (false !== $position) {
                        unset($keys[$position]);
                    }
                }
                $loadedItems = array_replace($loadedItems, $poolLoadedItems);
                if ([] !== $poolNotFoundItems) {
                    $notFoundItems[$poolKey] = $poolNotFoundItems;
                }
                if (\count($hits) === $keysCount) {
                    break;
                }
            } catch (\Exception $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }

        if (!$poolResponded) {
            throw new NoPoolAvailableException('No valid cache pool available for the chain.');
        }

        if (!empty($hits) && !empty($notFoundItems)) {
            foreach ($notFoundItems as $poolKey => $itemKeys) {
                try {
                    $pool = $this->getPools()[$poolKey];
                    $found = false;
                    foreach ($itemKeys as $itemKey) {
                        $mappedKey = "\0".$itemKey;
                        if (isset($hits[$mappedKey])) {
                            $found = true;
                            $pool->saveDeferred($this->createBackfillItem($hits[$mappedKey]));
                        }
                    }
                    if ($found) {
                        $pool->commit();
                    }
                } catch (\Exception $e) {
                    $this->handleException($poolKey, __FUNCTION__, $e);
                }
            }
        }

        return $this->generateItems(array_replace($loadedItems, $hits));
    }

    /**
     * @param array<array-key, mixed> $keys
     *
     * @return list<string>
     */
    private function prepareKeys(array $keys): array
    {
        $validatedKeys = [];
        foreach ($keys as $key) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException(\sprintf('Cache key must be string, "%s" given', get_debug_type($key)));
            }
            if ('' === $key) {
                throw new InvalidArgumentException('Cache key cannot be an empty string');
            }
            if (preg_match('|[\{\}\(\)/\\\\\@\:]|', $key)) {
                throw new InvalidArgumentException(\sprintf('Invalid key: "%s". The key contains one or more characters reserved for future extension: {}()/\@:', $key));
            }

            $validatedKeys["\0".$key] = $key;
        }

        return array_values($validatedKeys);
    }

    private function createBackfillItem(PhpCacheItem $item): PhpCacheItem
    {
        $backfill = new CacheItem($item->getKey(), true, $item->get());
        $backfill->setTags(array_replace($item->getPreviousTags(), $item->getTags()));

        if (null !== $expirationTimestamp = $item->getExpirationTimestamp()) {
            $backfill->expiresAt((new \DateTimeImmutable())->setTimestamp($expirationTimestamp));
        }

        return $backfill;
    }

    /**
     * @param array<string, PhpCacheItem> $items
     *
     * @return \Generator<string, PhpCacheItem>
     */
    private function generateItems(array $items): \Generator
    {
        foreach ($items as $item) {
            yield $item->getKey() => $item;
        }
    }

    public function hasItem(string $key): bool
    {
        $poolResponded = false;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $hasItem = $pool->hasItem($key);
                $poolResponded = true;
                if ($hasItem) {
                    return true;
                }
            } catch (\Exception $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }

        $this->ensurePoolResponded($poolResponded);

        return false;
    }

    public function clear(): bool
    {
        $result = true;
        $poolResponded = false;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $result = $pool->clear() && $result;
                $poolResponded = true;
            } catch (\Exception $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }

        $this->ensurePoolResponded($poolResponded);

        return $result;
    }

    public function deleteItem(string $key): bool
    {
        $result = true;
        $poolResponded = false;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $result = $pool->deleteItem($key) && $result;
                $poolResponded = true;
            } catch (\Exception $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }

        $this->ensurePoolResponded($poolResponded);

        return $result;
    }

    public function deleteItems(array $keys): bool
    {
        $result = true;
        $poolResponded = false;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $result = $pool->deleteItems($keys) && $result;
                $poolResponded = true;
            } catch (\Exception $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }

        $this->ensurePoolResponded($poolResponded);

        return $result;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof PhpCacheItem) {
            throw new InvalidArgumentException('Cache items are not transferable between pools. Item MUST implement PhpCacheItem.');
        }

        $result = true;
        $poolResponded = false;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $result = $pool->save($item) && $result;
                $poolResponded = true;
            } catch (\Exception $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }

        $this->ensurePoolResponded($poolResponded);

        return $result;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$item instanceof PhpCacheItem) {
            throw new InvalidArgumentException('Cache items are not transferable between pools. Item MUST implement PhpCacheItem.');
        }

        $result = true;
        $poolResponded = false;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $result = $pool->saveDeferred($item) && $result;
                $poolResponded = true;
            } catch (\Exception $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }

        $this->ensurePoolResponded($poolResponded);

        return $result;
    }

    public function commit(): bool
    {
        $result = true;
        $poolResponded = false;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $result = $pool->commit() && $result;
                $poolResponded = true;
            } catch (\Exception $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }

        $this->ensurePoolResponded($poolResponded);

        return $result;
    }

    public function invalidateTag(string $tag): bool
    {
        return $this->invalidateTags([$tag]);
    }

    /**
     * @param list<string> $tags
     */
    public function invalidateTags(array $tags): bool
    {
        $result = true;
        $poolResponded = false;
        foreach ($this->getPools() as $poolKey => $pool) {
            try {
                $result = $pool->invalidateTags($tags) && $result;
                $poolResponded = true;
            } catch (\Exception $e) {
                $this->handleException($poolKey, __FUNCTION__, $e);
            }
        }

        $this->ensurePoolResponded($poolResponded);

        return $result;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->simpleCacheBridge()->get($key, $default);
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        return $this->simpleCacheBridge()->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->simpleCacheBridge()->delete($key);
    }

    /**
     * @param iterable<mixed> $keys
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->simpleCacheBridge()->getMultiple($keys, $default);
    }

    /**
     * @param iterable<array-key, mixed> $values
     */
    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        return $this->simpleCacheBridge()->setMultiple($values, $ttl);
    }

    /**
     * @param iterable<mixed> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        return $this->simpleCacheBridge()->deleteMultiple($keys);
    }

    public function has(string $key): bool
    {
        return $this->simpleCacheBridge()->has($key);
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
     * @return array<array-key, PhpCachePool>
     */
    protected function getPools(): array
    {
        if (empty($this->pools)) {
            throw new NoPoolAvailableException('No valid cache pool available for the chain.');
        }

        return $this->pools;
    }

    private function handleException(int|string $poolKey, string $operation, \Exception $exception): void
    {
        if ($exception instanceof CacheInvalidArgumentException || !$this->options['skip_on_failure']) {
            throw $exception;
        }

        $this->log(
            'warning',
            \sprintf(
                'Removing pool "%s" from chain because it threw an exception when executing "%s"',
                $poolKey,
                $operation
            ),
            ['exception' => $exception]
        );

        unset($this->pools[$poolKey]);
    }

    private function ensurePoolResponded(bool $poolResponded): void
    {
        if (!$poolResponded) {
            throw new NoPoolAvailableException('No valid cache pool available for the chain.');
        }
    }

    private function simpleCacheBridge(): SimpleCacheBridge
    {
        return new SimpleCacheBridge($this);
    }
}
