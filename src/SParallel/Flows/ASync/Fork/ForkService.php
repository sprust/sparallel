<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Fork;

readonly class ForkService
{
    public function isFinished(int $pid): bool
    {
        $status = pcntl_waitpid($pid, $status, WNOHANG | WUNTRACED);

        if ($status === $pid) {
            return true;
        }

        if ($status === -1) {
            return true;
        }

        return false;
    }

    public function waitFinishAllChildren(): void
    {
        while (pcntl_waitpid(0, $status) != -1) {
            usleep(100);
        }
    }

    public function finish(int $pid): void
    {
        posix_kill($pid, SIGTERM);

        while (pcntl_waitpid($pid, $status) !== -1) {
            usleep(100);
        }
    }
}
