<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync;

use Closure;
use Generator;
use Psr\Log\LoggerInterface;
use SParallel\Contracts\FlowInterface;
use SParallel\Contracts\TaskInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Exceptions\UnexpectedTaskException;
use SParallel\Exceptions\UnexpectedTaskTerminationException;
use SParallel\Objects\TaskResult;
use SParallel\Services\Context;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\MessageTransport;
use SParallel\Transport\ResultTransport;

class ASyncFlow implements FlowInterface
{
    protected Context $context;

    /**
     * @var array<int|string, Closure>
     */
    protected array $callbacks;

    protected int $workersLimit;

    protected DriverInterface $driver;

    /**
     * @var array<int|string, TaskInterface>
     */
    protected array $activeTasks;

    /**
     * @var array<int|string>
     */
    protected array $remainTaskKeys;

    protected bool $isFinished;

    public function __construct(
        protected ContextTransport $contextTransport,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected MessageTransport $messageTransport,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * @param array<int|string, Closure> $callbacks
     */
    public function start(
        Context $context,
        DriverInterface $driver,
        array &$callbacks,
        int $workersLimit,
    ): static {
        $this->logger->debug(
            sprintf(
                "async flow started [dr: %s]",
                $driver::class
            )
        );

        $this->context      = $context;
        $this->callbacks    = $callbacks;
        $this->workersLimit = $workersLimit;
        $this->driver       = $driver;

        $this->isFinished = false;

        $this->activeTasks = [];

        $this->remainTaskKeys = array_keys($this->callbacks);

        $this->driver->init(
            context: $context,
            callbacks: $callbacks,
            workersLimit: $workersLimit,
        );

        // TODO: do pretty
        $callbacks = [];

        $this->shiftWorkers();

        return $this;
    }

    public function get(): Generator
    {
        while (!$this->isFinished) {
            $this->context->check();

            $this->shiftWorkers();

            if (!count($this->activeTasks)) {
                break;
            }

            $currentActiveTasks = $this->activeTasks;

            $taskKeys = array_keys($currentActiveTasks);

            foreach ($taskKeys as $taskKey) {
                $task = $this->activeTasks[$taskKey];

                if ($task->isFinished()) {
                    $task->finish();

                    unset($this->activeTasks[$taskKey]);

                    $this->logger->debug(
                        sprintf(
                            "async flow deleted task from active [tKey: %s, tPid: %d]",
                            $task->getKey(),
                            $task->getPid()
                        )
                    );
                }
            }

            while (true) {
                $taskResult = $this->driver->getResult($this->context);

                if ($taskResult === false) {
                    $this->context->check();

                    usleep(100);

                    break;
                }

                $task = $currentActiveTasks[$taskResult->taskKey] ?? null;

                if (!$task) {
                    throw new UnexpectedTaskException(
                        unexpectedTaskKey: $taskResult->taskKey,
                    );
                }

                $this->logger->debug(
                    sprintf(
                        "async flow got result from task [rTKey: %s tKey: %s, tPid: %d]",
                        $taskResult->taskKey,
                        $task->getKey(),
                        $task->getPid()
                    )
                );

                $this->deleteRemainTaskKeys($task->getKey());

                yield $taskResult;
            }
        }

        while (true) {
            $task = $this->pullTask();

            if (!$task) {
                break;
            }

            $taskKey = $task->getKey();

            $this->deleteRemainTaskKeys($taskKey);

            $output = $task->getOutput();

            $task->finish();

            yield new TaskResult(
                taskKey: $taskKey,
                exception: new UnexpectedTaskTerminationException(
                    taskKey: $taskKey,
                    description: $output
                )
            );
        }

        while (count($this->remainTaskKeys) > 0) {
            $taskKey = array_shift($this->remainTaskKeys);

            yield new TaskResult(
                taskKey: $taskKey,
                exception: new UnexpectedTaskTerminationException(
                    taskKey: $taskKey
                )
            );
        }
    }

    public function break(): void
    {
        if ($this->isFinished) {
            return;
        }

        while (true) {
            $task = $this->pullTask();

            if (!$task) {
                break;
            }

            $task->finish();
        }

        if (isset($this->driver)) {
            $this->driver->break($this->context);
        }

        $this->isFinished = true;

        $this->logger->debug(
            'async flow stopped'
        );
    }

    protected function shiftWorkers(): void
    {
        $activeTasksCount = count($this->activeTasks);

        if ($activeTasksCount >= $this->workersLimit || $activeTasksCount >= count($this->remainTaskKeys)) {
            return;
        }

        $taskKeys = array_slice(
            array: array_keys($this->callbacks),
            offset: 0,
            length: $this->workersLimit - $activeTasksCount
        );

        foreach ($taskKeys as $taskKey) {
            $callback = $this->callbacks[$taskKey];

            $task = $this->driver->createTask(
                context: $this->context,
                taskKey: $taskKey,
                callback: $callback
            );

            $this->logger->debug(
                sprintf(
                    "async flow created task [tKey: %s, tPid: %s]",
                    $taskKey,
                    $task->getPid()
                )
            );

            $this->activeTasks[$taskKey] = $task;

            unset($this->callbacks[$taskKey]);
        }
    }

    protected function pullTask(): ?TaskInterface
    {
        return array_shift($this->activeTasks);
    }

    protected function deleteRemainTaskKeys(int|string $taskKey): void
    {
        $this->remainTaskKeys = array_filter(
            $this->remainTaskKeys,
            static fn(int|string $remainTaskKey) => $remainTaskKey !== $taskKey
        );
    }

    public function __destruct()
    {
        $this->break();
    }
}
