<?php

declare(strict_types=1);

namespace SParallel\Objects;

use Socket;

class SocketServerObject
{
    protected bool $isClosed = false;

    public function __construct(
        public readonly string $path,
        public readonly Socket $socket
    ) {
    }

    public function close(): void
    {
        $this->isClosed = true;
    }

    public function isClosed(): bool
    {
        return $this->isClosed;
    }

    public function __destruct()
    {
        @unlink($this->path);
    }
}
