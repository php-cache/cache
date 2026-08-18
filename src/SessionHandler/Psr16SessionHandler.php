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

use Psr\SimpleCache\CacheInterface;

/**
 * @author Daniel Bannert <d.bannert@anolilab.de>
 */
class Psr16SessionHandler extends AbstractSessionHandler
{
    private CacheInterface $cache;

    private int $ttl;

    private string $prefix;

    /**
     * @param array{ttl?: int, prefix?: string} $options
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(CacheInterface $cache, SessionLockInterface $lock, array $options = [])
    {
        parent::__construct($lock);

        $this->cache = $cache;

        if ($diff = array_diff(array_keys($options), ['prefix', 'ttl'])) {
            throw new \InvalidArgumentException(sprintf('The following options are not supported "%s"', implode(', ', $diff)));
        }

        $this->ttl = isset($options['ttl']) ? (int) $options['ttl'] : 86400;
        $this->prefix = isset($options['prefix']) ? (string) $options['prefix'] : 'psr16ses_';
    }

    protected function doUpdateTimestamp(string $sessionId, string $data): bool
    {
        $value = $this->cache->get($this->prefix.$sessionId);

        if (null === $value) {
            return false;
        }

        return $this->cache->set(
            $this->prefix.$sessionId,
            $value,
            $this->ttl
        );
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function doRead(string $sessionId): string
    {
        $data = $this->cache->get($this->prefix.$sessionId, '');

        return is_string($data) ? $data : '';
    }

    protected function doWrite(string $sessionId, string $data): bool
    {
        return $this->cache->set($this->prefix.$sessionId, $data, $this->ttl);
    }

    protected function doDestroy(string $sessionId): bool
    {
        return $this->cache->delete($this->prefix.$sessionId);
    }
}
