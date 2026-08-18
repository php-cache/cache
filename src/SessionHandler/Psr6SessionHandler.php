<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\SessionHandler;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Daniel Bannert <d.bannert@anolilab.de>
 */
class Psr6SessionHandler extends AbstractSessionHandler
{
    private CacheItemPoolInterface $cache;

    private int $ttl;

    private string $prefix;

    /**
     * @param array{ttl?: int, prefix?: string} $options
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(CacheItemPoolInterface $cache, SessionLockInterface $lock, array $options = [])
    {
        parent::__construct($lock);

        $this->cache = $cache;

        if ($diff = array_diff(array_keys($options), ['prefix', 'ttl'])) {
            throw new \InvalidArgumentException(\sprintf('The following options are not supported "%s"', implode(', ', $diff)));
        }

        $this->ttl = isset($options['ttl']) ? (int) $options['ttl'] : 86400;
        $this->prefix = isset($options['prefix']) ? (string) $options['prefix'] : 'psr6ses_';
    }

    protected function doUpdateTimestamp(string $sessionId, string $data): bool
    {
        $item = $this->getCacheItem($sessionId);
        if (!$item->isHit()) {
            return false;
        }

        $item->expiresAfter($this->ttl);

        return $this->cache->save($item);
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function doRead(string $sessionId): string
    {
        $item = $this->getCacheItem($sessionId);

        if ($item->isHit()) {
            $data = $item->get();

            return \is_string($data) ? $data : '';
        }

        return '';
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function doWrite(string $sessionId, string $data): bool
    {
        $item = $this->getCacheItem($sessionId);
        $item->set($data)
            ->expiresAfter($this->ttl);

        return $this->cache->save($item);
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function doDestroy(string $sessionId): bool
    {
        return $this->cache->deleteItem($this->prefix.$sessionId);
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function getCacheItem(string $sessionId): CacheItemInterface
    {
        return $this->cache->getItem($this->prefix.$sessionId);
    }
}
