<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Apcu;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Adapter\Common\PhpUnserializer;
use Cache\Adapter\Common\TagSupportWithArray;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ApcuCachePool extends AbstractCachePool
{
    use TagSupportWithArray;

    private bool $skipOnCli;

    public function __construct(bool $skipOnCli = false)
    {
        $this->skipOnCli = $skipOnCli;
    }

    protected function fetchObjectFromCache(string $key): array
    {
        if ($this->skipIfCli()) {
            return [false, null, [], null];
        }

        $success = false;
        $complete = PhpUnserializer::decodeWith(static function () use ($key, &$success): mixed {
            return apcu_fetch($key, $success);
        }, $record);
        if (!$complete || !$success || !\is_array($record) || !array_is_list($record) || 3 !== \count($record)) {
            return [false, null, [], null];
        }

        $tags = $record[1];
        if (!\is_array($tags)) {
            return [false, null, [], null];
        }

        $decodedTags = [];
        foreach ($tags as $tagVersion) {
            if (!\is_array($tagVersion) || !array_is_list($tagVersion) || 2 !== \count($tagVersion)) {
                return [false, null, [], null];
            }
            [$tag, $version] = $tagVersion;
            if (!\is_string($tag) || !\is_string($version)) {
                return [false, null, [], null];
            }

            $decodedTags[] = [$tag, $version];
        }

        $expiration = $record[2];
        if (!\is_int($expiration) && null !== $expiration) {
            return [false, null, [], null];
        }

        return [true, $record[0], $decodedTags, $expiration];
    }

    protected function clearAllObjectsFromCache(): bool
    {
        return apcu_clear_cache();
    }

    protected function clearOneObjectFromCache(string $key): bool
    {
        return apcu_delete($key) || !apcu_exists($key);
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        if ($this->skipIfCli()) {
            return false;
        }

        if ($ttl < 0) {
            return false;
        }

        if (null === $ttl) {
            $ttl = 0;
        }

        return apcu_store($item->getKey(), [$item->get(), $item->getTagVersions(), $item->getExpirationTimestamp()], $ttl);
    }

    /**
     * Returns true if CLI and if it should skip on cli.
     */
    private function skipIfCli(): bool
    {
        return $this->skipOnCli && 'cli' === \PHP_SAPI;
    }

    public function getDirectValue(string $name): mixed
    {
        return PhpUnserializer::decodeWith(static fn (): mixed => apcu_fetch($name), $value) ? $value : null;
    }

    public function setDirectValue(string $name, mixed $value): bool
    {
        return apcu_store($name, $value);
    }
}
