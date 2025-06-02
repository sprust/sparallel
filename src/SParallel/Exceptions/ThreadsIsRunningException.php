<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;

class ThreadsIsRunningException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Threads is already running',
        );
    }
}
