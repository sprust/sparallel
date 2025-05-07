<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Process;

use Closure;
use SParallel\Contracts\TaskInterface;
use SParallel\Services\Process\ProcessService;
use Symfony\Component\Process\Process;

readonly class ProcessTask implements TaskInterface
{
    public function __construct(
        protected int $pid,
        protected int|string $taskKey,
        protected Closure $callback,
        protected Process $process,
        protected ProcessService $processService,
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
        return !$this->process->isRunning();
    }

    public function finish(): void
    {
        $this->process->stop();
    }

    public function getOutput(): ?string
    {
        return $this->processService->getOutput($this->process);
    }

    public function getCallback(): Closure
    {
        return $this->callback;
    }
}
