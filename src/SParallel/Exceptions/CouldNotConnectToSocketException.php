<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;
use Socket;

class CouldNotConnectToSocketException extends RuntimeException
{
    public function __construct(public readonly Socket $socket)
    {
        parent::__construct(
            'Could not connect to socket: ' . socket_strerror(socket_last_error($socket))
        );
    }
}
