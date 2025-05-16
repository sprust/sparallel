<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Process;

use Closure;
use Psr\Log\LoggerInterface;
use SParallel\Contracts\TaskInterface;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;

readonly class ProcessTask implements TaskInterface
{
    public function __construct(
        protected Context $context,
        protected int $pid,
        protected int|string $taskKey,
        protected Closure $callback,
        protected Process $process,
        protected ProcessService $processService,
        protected ProcessDriver $processDriver,
        protected LoggerInterface $logger,
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
        return !$this->processDriver->isTaskActive($this->taskKey);
    }

    /**
     * @throws ContextCheckerException
     */
    public function finish(): void
    {
        $this->processService->killChildren(
            context: $this->context,
            caller: 'process task',
            pid: $this->pid
        );

        $this->process->stop();

        while ($this->process->isRunning()) {
            $this->context->check();

            usleep(100);
        }

        $this->logger->debug(
            sprintf(
                "process task stops process [pPid: %s]",
                $this->pid
            )
        );
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
