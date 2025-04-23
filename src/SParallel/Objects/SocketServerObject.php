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

    public function __destruct()
    {
        socket_close($this->socket);
        @unlink($this->path);
    }
}
