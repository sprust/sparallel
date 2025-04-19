<?php

declare(strict_types=1);

namespace SParallel\Drivers;

use RuntimeException;
use SParallel\Exceptions\SParallelTimeoutException;

readonly class Timer
{
    public int $startTime;

    public function __construct(
        public int $timeoutSeconds,
        ?int $customStartTime = null,
    ) {
        if ($this->timeoutSeconds < 1) {
            throw new RuntimeException(
                'Timeout seconds must be greater than 0'
            );
        }

        $this->startTime = is_null($customStartTime) ? time() : $customStartTime;
    }

    /**
     * @throws SParallelTimeoutException
     */
    public function check(): void
    {
        if ($this->isTimeout()) {
            throw new SParallelTimeoutException();
        }
    }

    public function isTimeout(): bool
    {
        return (time() - $this->startTime) > $this->timeoutSeconds;
    }
}
