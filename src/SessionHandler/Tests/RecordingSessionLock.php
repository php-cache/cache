<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\SessionHandler\Tests;

use Cache\SessionHandler\SessionLockInterface;

final class RecordingSessionLock implements SessionLockInterface
{
    public int $acquireCalls = 0;

    public int $releaseCalls = 0;

    public ?string $acquiredSessionId = null;

    public ?string $releasedSessionId = null;

    public string $events = '';

    public function __construct(private bool $acquired = true)
    {
    }

    public function acquire(string $sessionId): bool
    {
        ++$this->acquireCalls;
        $this->acquiredSessionId = $sessionId;
        $this->events .= 'acquire '.$sessionId."\n";

        return $this->acquired;
    }

    public function release(string $sessionId): void
    {
        ++$this->releaseCalls;
        $this->releasedSessionId = $sessionId;
        $this->events .= 'release '.$sessionId."\n";
    }
}
