<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Redis;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\Exception\CachePoolException;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Adapter\Common\PhpUnserializer;
use Cache\Hierarchy\HierarchicalCachePoolTrait;
use Cache\Hierarchy\HierarchicalPoolInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class RedisCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;

    private const TAG_GENERATION_MEMBER_PREFIX = '@generation:';

    private const TAG_KEY_PREFIX = 'php-cache:tag:';

    private const APPEND_TAG_SCRIPT = <<<'LUA'
        redis.call('ZREMRANGEBYSCORE', KEYS[1], 1, ARGV[3])
        redis.call('ZADD', KEYS[1], ARGV[2], ARGV[1])

        if redis.call('ZCOUNT', KEYS[1], 0, 0) > 0 then
            redis.call('PERSIST', KEYS[1])
        else
            local latest = redis.call('ZREVRANGE', KEYS[1], 0, 0, 'WITHSCORES')
            local ttl = math.max(1, latest[2] - ARGV[3])
            redis.call('EXPIRE', KEYS[1], ttl)
        end

        return 1
        LUA;

    private const REMOVE_TAG_SCRIPT = <<<'LUA'
        redis.call('ZREMRANGEBYSCORE', KEYS[1], 1, ARGV[2])
        local removed = redis.call('ZREM', KEYS[1], ARGV[1])

        if redis.call('ZCOUNT', KEYS[1], 0, '+inf') == 0 then
            if redis.call('ZCOUNT', KEYS[1], -1, -1) > 0 then
                redis.call('EXPIRE', KEYS[1], 60)
            else
                redis.call('DEL', KEYS[1])
            end
            return removed
        end

        if redis.call('ZCOUNT', KEYS[1], 0, 0) > 0 then
            redis.call('PERSIST', KEYS[1])
        else
            local latest = redis.call('ZREVRANGE', KEYS[1], 0, 0, 'WITHSCORES')
            local ttl = math.max(1, latest[2] - ARGV[2])
            redis.call('EXPIRE', KEYS[1], ttl)
        end

        return removed
        LUA;

    private const READ_TAG_SCRIPT = <<<'LUA'
        redis.call('ZREMRANGEBYSCORE', KEYS[1], 1, ARGV[1])

        return redis.call('ZRANGEBYSCORE', KEYS[1], 0, '+inf')
        LUA;

    private const READ_TAG_VERSION_SCRIPT = <<<'LUA'
        local versions = redis.call('ZRANGEBYSCORE', KEYS[1], -1, -1, 'LIMIT', 0, 1)

        return versions[1] or false
        LUA;

    private const WRITE_TAG_VERSION_SCRIPT = <<<'LUA'
        if redis.call('ZCOUNT', KEYS[1], -1, -1) == 0 then
            redis.call('ZADD', KEYS[1], -1, '@generation:' .. ARGV[1])
        end

        if redis.call('ZCOUNT', KEYS[1], 0, '+inf') == 0 then
            redis.call('EXPIRE', KEYS[1], 60)
        end

        return 1
        LUA;

    protected \Redis|\RedisArray|\RedisCluster $cache;

    /** @var array<string, true> */
    private array $failedListReads = [];

    public function __construct(mixed $cache)
    {
        if (!$cache instanceof \Redis
            && !$cache instanceof \RedisArray
            && !$cache instanceof \RedisCluster
        ) {
            throw new CachePoolException('Cache instance must be of type \Redis, \RedisArray, or \RedisCluster');
        }

        $this->cache = $cache;
    }

    protected function fetchObjectFromCache(string $key): array
    {
        return $this->decodeCacheItem($this->cache->get($this->getHierarchyKey($key))) ?? [false, null, [], null];
    }

    protected function clearAllObjectsFromCache(): bool
    {
        if ($this->cache instanceof \RedisCluster) {
            return $this->clearAllObjectsFromCacheCluster();
        }

        $result = $this->cache->flushDb();

        if (!\is_array($result)) {
            return true === $result;
        }

        $success = true;

        foreach ($result as $serverResult) {
            if (!$serverResult) {
                $success = false;
                break;
            }
        }

        return $success;
    }

    /**
     * Clear all objects from all nodes in the cluster.
     *
     * @return bool false if error
     */
    protected function clearAllObjectsFromCacheCluster(): bool
    {
        if (!$this->cache instanceof \RedisCluster) {
            return false;
        }

        $nodes = $this->cache->_masters();

        foreach ($nodes as $node) {
            if (!$this->cache->flushDB($node)) {
                return false;
            }
        }

        return true;
    }

    protected function clearOneObjectFromCache(string $key): bool
    {
        $path = null;
        $keyString = $this->getHierarchyKey($key, $path);
        $generationAdvanced = true;
        if (null !== $path) {
            $generationAdvanced = false !== $this->cache->incr($path);
        }
        $this->clearHierarchyKeyCache();

        $deleted = $this->cache->del($keyString);

        return $generationAdvanced && \is_int($deleted) && $deleted >= 0;
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        $key = $this->getHierarchyKey($item->getKey());
        $data = serialize([true, $item->get(), $item->getTagVersions(), $item->getExpirationTimestamp()]);
        if (null === $ttl || 0 === $ttl) {
            return $this->isSuccessfulWrite($this->cache->set($key, $data));
        }

        return $this->isSuccessfulWrite($this->cache->setex($key, $ttl, $data));
    }

    public function getDirectValue(string $key): mixed
    {
        return $this->cache->get($key);
    }

    protected function getTagKey(string $tag): string
    {
        return self::TAG_KEY_PREFIX.$tag;
    }

    protected function getTagVersionKey(string $tag): string
    {
        return $this->getTagKey($tag);
    }

    protected function readTagVersion(string $name): ?string
    {
        $stored = $this->evaluateTagScript(self::READ_TAG_VERSION_SCRIPT, $name);
        if (!\is_string($stored) || '' === $stored || !str_starts_with($stored, self::TAG_GENERATION_MEMBER_PREFIX)) {
            return null;
        }

        return substr($stored, \strlen(self::TAG_GENERATION_MEMBER_PREFIX));
    }

    protected function writeTagVersion(string $name, string $version): bool
    {
        return 1 === $this->evaluateTagScript(self::WRITE_TAG_VERSION_SCRIPT, $name, $version);
    }

    protected function deleteTagVersion(string $name): bool
    {
        $deleted = $this->cache->del($name);

        return \is_int($deleted) && $deleted >= 0;
    }

    protected function appendListItem(string $name, string $value): bool
    {
        return $this->appendListItemWithExpiration($name, $value, null);
    }

    protected function appendListItemWithExpiration(string $name, string $key, ?int $expirationTimestamp): bool
    {
        $added = $this->evaluateTagScript(
            self::APPEND_TAG_SCRIPT,
            $name,
            $key,
            $expirationTimestamp ?? 0,
            time()
        );

        return 1 === $added;
    }

    protected function getList(string $name): array
    {
        $items = $this->evaluateTagScript(self::READ_TAG_SCRIPT, $name, time());
        if (!\is_array($items)) {
            $this->failedListReads[$name] = true;

            return [];
        }

        unset($this->failedListReads[$name]);

        return array_values(array_filter($items, is_string(...)));
    }

    protected function removeList(string $name): bool
    {
        if (isset($this->failedListReads[$name])) {
            unset($this->failedListReads[$name]);

            return false;
        }

        $deleted = $this->cache->del($name);

        return \is_int($deleted) && $deleted >= 0;
    }

    protected function removeListItem(string $name, string $key): bool
    {
        $removed = $this->evaluateTagScript(self::REMOVE_TAG_SCRIPT, $name, $key, time());

        return \is_int($removed) && $removed >= 0;
    }

    private function evaluateTagScript(string $script, string $name, int|string ...$arguments): mixed
    {
        $scriptArguments = [$name, ...$arguments];
        if (!$this->cache instanceof \RedisArray) {
            return $this->cache->eval($script, $scriptArguments, 1);
        }

        $host = (string) $this->cache->_target($name);
        if ('' === $host) {
            return false;
        }

        $node = $this->cache->_instance($host);
        if (!$node instanceof \Redis) {
            return false;
        }

        return $node->eval($script, $scriptArguments, 1);
    }

    /**
     * @return array{true, mixed, list<array{0: string, 1: string}>, int|null}|null
     */
    private function decodeCacheItem(mixed $payload): ?array
    {
        if (!\is_string($payload)) {
            return null;
        }

        if (!PhpUnserializer::unserialize($payload, $cacheItem)) {
            return null;
        }
        if (!\is_array($cacheItem) || !array_is_list($cacheItem) || 4 !== \count($cacheItem)) {
            return null;
        }

        [$hit, $value, $tags, $expirationTimestamp] = $cacheItem;
        if (true !== $hit || !\is_array($tags)) {
            return null;
        }

        $validTags = [];
        foreach ($tags as $tagVersion) {
            if (!\is_array($tagVersion) || !array_is_list($tagVersion) || 2 !== \count($tagVersion)) {
                return null;
            }
            [$tag, $version] = $tagVersion;
            if (!\is_string($tag) || !\is_string($version)) {
                return null;
            }

            $validTags[] = [$tag, $version];
        }

        if (null !== $expirationTimestamp && !\is_int($expirationTimestamp)) {
            return null;
        }

        return [true, $value, $validTags, $expirationTimestamp];
    }

    private function isSuccessfulWrite(mixed $response): bool
    {
        return true === $response || 'OK' === $response;
    }
}
