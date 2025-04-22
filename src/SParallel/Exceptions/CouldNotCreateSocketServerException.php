<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;

class CouldNotCreateSocketServerException extends RuntimeException
{
    public function __construct(public readonly string $socketPath)
    {
        parent::__construct(
            "Could not create socket by [$socketPath]: " . socket_strerror(socket_last_error())
        );
    }
}
