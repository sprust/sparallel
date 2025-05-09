<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Hybrid;

use Closure;
use SParallel\Contracts\TaskInterface;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use Symfony\Component\Process\Process;

readonly class HybridTask implements TaskInterface
{
    public function __construct(
        protected Context $context,
        protected int $pid,
        protected int|string $taskKey,
        protected Closure $callback,
        protected Process $process,
        protected ProcessService $processService,
        protected HybridDriver $hybridDriver,
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

    /**
     * @throws ContextCheckerException
     */
    public function isFinished(): bool
    {
        // TODO: implement otherwise
        //if (!$this->process->isRunning()) {
        //    return true;
        //}

        return $this->hybridDriver->isTaskFinished(
            context: $this->context,
            taskKey: $this->taskKey,
        );
    }

    public function finish(): void
    {
        //
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
