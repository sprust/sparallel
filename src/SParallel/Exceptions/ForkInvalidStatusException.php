<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;

class ForkInvalidStatusException extends RuntimeException
{
    public function __construct(public readonly int $pid)
    {
        parent::__construct(
            "Could not reliably manage task that uses process id [$pid]"
        );
    }
}
