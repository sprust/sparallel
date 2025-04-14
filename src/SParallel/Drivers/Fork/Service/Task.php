<?php

namespace SParallel\Drivers\Fork\Service;

use RuntimeException;

class Task
{
    protected string $output = '';

    public function __construct(protected int $pid, protected Connection $connection)
    {
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function output(): string
    {
        foreach ($this->connection->read() as $output) {
            $this->output .= $output;
        }

        $this->connection->close();

        return $this->output;
    }

    public function isFinished(): bool
    {
        foreach ($this->connection->read() as $output) {
            $this->output .= $output;
        }

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
