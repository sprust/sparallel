<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork\Service;

use RuntimeException;
use Socket;

readonly class Task
{
    public function __construct(public int $pid, public Socket $socket)
    {
    }

    public function isFinished(): bool
    {
        $status = pcntl_waitpid($this->pid, $status, WNOHANG | WUNTRACED);

        if ($status === $this->pid) {
            return true;
        }

        if ($status !== 0) {
            throw new RuntimeException(
                "Could not reliably manage task that uses process id [$this->pid]"
            );
        }

        return false;
    }
}
