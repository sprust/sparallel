<?php

declare(strict_types=1);

namespace SParallel\Entities;

use Socket;

readonly class SocketServer
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
