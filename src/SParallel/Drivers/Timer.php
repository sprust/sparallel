<?php

declare(strict_types=1);

namespace SParallel\Drivers;

use SParallel\Contracts\ContextCheckerInterface;
use SParallel\Exceptions\InvalidValueException;
use SParallel\Exceptions\SParallelTimeoutException;

readonly class Timer implements ContextCheckerInterface
{
    public int $startTime;

    public function __construct(
        public int $timeoutSeconds,
        ?int $customStartTime = null,
    ) {
        if ($this->timeoutSeconds < 1) {
            throw new InvalidValueException(
                'Timeout seconds must be greater than 0'
            );
        }

        if (!is_null($customStartTime) && ($customStartTime < 1 || $customStartTime > time())) {
            throw new InvalidValueException(
                'Custom start time must be greater than 0 and less than current time'
            );
        }

        $this->startTime = is_null($customStartTime) ? time() : $customStartTime;
    }

    public function check(): void
    {
        if ($this->isTimeout()) {
            throw new SParallelTimeoutException();
        }
    }

    protected function isTimeout(): bool
    {
        return (time() - $this->startTime) > $this->timeoutSeconds;
    }
}
