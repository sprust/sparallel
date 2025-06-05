<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;

class ConcurrencyIsRunningException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Concurrency is already running',
        );
    }
}
