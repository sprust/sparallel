<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Fork;

use Closure;
use SParallel\Contracts\TaskInterface;

class ForkTask implements TaskInterface
{
    public function __construct(
        protected int $pid,
        protected int|string $taskKey,
        protected Closure $callback,
        protected ForkService $forkService,
    ) {
    }

    public function getKey(): int|string
    {
        return $this->taskKey;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function isFinished(): bool
    {
        return $this->forkService->isFinished($this->pid);
    }

    public function finish(): void
    {
        $this->forkService->finish($this->pid);
    }

    public function getOutput(): ?string
    {
        return null;
    }

    public function getCallback(): Closure
    {
        return $this->callback;
    }
}
