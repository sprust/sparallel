<?php

declare(strict_types=1);

namespace SParallel\Objects;

use Socket;

readonly class SocketServerObject
{
    public function __construct(
        public string $path,
        public Socket $socket
    ) {
    }
}
