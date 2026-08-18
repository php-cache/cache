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

/**
 * @author Daniel Bannert <d.bannert@anolilab.de>
 *
 * ported from https://github.com/symfony/symfony/blob/master/src/Symfony/Component/HttpFoundation/Session/Storage/Handler/AbstractSessionHandler.php
 */
abstract class AbstractSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    private ?string $lockedSessionId = null;

    private ?string $prefetchId = null;

    private ?string $prefetchData = null;

    private ?string $newSessionId = null;

    private ?string $igbinaryEmptyData = null;

    public function __construct(private SessionLockInterface $lock)
    {
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function validateId(string $sessionId): bool
    {
        $data = $this->read($sessionId);

        if (false === $data) {
            return false;
        }

        $this->prefetchData = $data;
        $this->prefetchId = $sessionId;

        return '' !== $this->prefetchData;
    }

    public function read(string $sessionId): string|false
    {
        if (!$this->acquireSessionLock($sessionId)) {
            return false;
        }

        try {
            if (null !== $this->prefetchId) {
                $prefetchId = $this->prefetchId;
                $prefetchData = $this->prefetchData ?? '';

                $this->prefetchId = $this->prefetchData = null;

                if ($prefetchId === $sessionId || '' === $prefetchData) {
                    $this->newSessionId = '' === $prefetchData ? $sessionId : null;

                    return $prefetchData;
                }
            }

            $data = $this->doRead($sessionId);
            $this->newSessionId = '' === $data ? $sessionId : null;

            return $data;
        } catch (\Throwable $exception) {
            $this->releaseSessionLock();

            throw $exception;
        }
    }

    public function write(string $sessionId, string $data): bool
    {
        if (!$this->acquireSessionLock($sessionId)) {
            return false;
        }

        try {
            if (null === $this->igbinaryEmptyData) {
                // see igbinary/igbinary/issues/146
                $this->igbinaryEmptyData = \function_exists('igbinary_serialize') ? (string) igbinary_serialize([]) : '';
            }

            if ('' === $data || $this->igbinaryEmptyData === $data) {
                return $this->destroy($sessionId);
            }

            $this->newSessionId = null;

            return $this->doWrite($sessionId, $data);
        } catch (\Throwable $exception) {
            $this->releaseSessionLock();

            throw $exception;
        }
    }

    public function destroy(string $sessionId): bool
    {
        if (!$this->acquireSessionLock($sessionId)) {
            return false;
        }

        try {
            return $this->newSessionId === $sessionId || $this->doDestroy($sessionId);
        } finally {
            $this->releaseSessionLock();
        }
    }

    public function close(): bool
    {
        $this->releaseSessionLock();

        return true;
    }

    public function gc(int $lifetime): int|false
    {
        return 0;
    }

    public function updateTimestamp(string $sessionId, string $data): bool
    {
        if (!$this->acquireSessionLock($sessionId)) {
            return false;
        }

        try {
            return $this->doUpdateTimestamp($sessionId, $data);
        } catch (\Throwable $exception) {
            $this->releaseSessionLock();

            throw $exception;
        }
    }

    private function acquireSessionLock(string $sessionId): bool
    {
        if ($sessionId === $this->lockedSessionId) {
            return true;
        }

        $this->releaseSessionLock();

        if (!$this->lock->acquire($sessionId)) {
            return false;
        }

        $this->lockedSessionId = $sessionId;

        return true;
    }

    private function releaseSessionLock(): void
    {
        $this->prefetchId = $this->prefetchData = null;

        if (null === $this->lockedSessionId) {
            return;
        }

        $sessionId = $this->lockedSessionId;
        $this->lock->release($sessionId);
        $this->lockedSessionId = null;
    }

    abstract protected function doRead(string $sessionId): string;

    abstract protected function doWrite(string $sessionId, string $data): bool;

    abstract protected function doDestroy(string $sessionId): bool;

    abstract protected function doUpdateTimestamp(string $sessionId, string $data): bool;
}
