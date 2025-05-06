<?php

declare(strict_types=1);

namespace SParallel\Services\Fork;

use SParallel\Exceptions\ForkInvalidStatusException;

readonly class ForkService
{
    public function isFinished(int $pid): bool
    {
        $status = pcntl_waitpid($pid, $status, WNOHANG | WUNTRACED);

        if ($status === $pid) {
            return true;
        }

        if ($status !== 0) {
            throw new ForkInvalidStatusException($pid);
        }

        return false;
    }

    public function finish(int $pid): void
    {
        pcntl_waitpid($pid, $status);

        posix_kill($pid, SIGKILL);
    }
}
