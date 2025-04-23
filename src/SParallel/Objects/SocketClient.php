<?php

declare(strict_types=1);

namespace SParallel\Objects;

use Socket;

readonly class SocketClient
{
    public function __construct(
        public Socket $socket
    ) {
    }

    public function __destruct()
    {
        socket_close($this->socket);
    }
}
