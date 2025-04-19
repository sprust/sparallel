<?php

declare(strict_types=1);

namespace SParallel\Drivers;

use SParallel\Exceptions\SParallelTimeoutException;

readonly class Timer
{
    protected int $startTime;

    public function __construct(
        protected int $timeoutSeconds,
    ) {
        $this->startTime = time();
    }

    /**
     * @throws SParallelTimeoutException
     */
    public function check(): void
    {
        if ($this->timeoutSeconds > 0 && (time() - $this->startTime) > $this->timeoutSeconds) {
            throw new SParallelTimeoutException();
        }
    }
}
