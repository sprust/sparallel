<?php

declare(strict_types=1);

namespace SParallel\Drivers\Server;

use Closure;
use Generator;
use RuntimeException;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Sync\SyncWaitGroup;
use SParallel\Entities\Context;
use SParallel\Exceptions\RpcCallException;
use SParallel\Exceptions\UnexpectedServerTaskException;
use SParallel\Objects\TaskResult;
use SParallel\Server\Dto\ServerTask;
use SParallel\Server\Workers\WorkersRpcClient;
use SParallel\Transport\ServerTaskTransport;
use SParallel\Transport\TaskResultTransport;
use Throwable;

class ServerWaitGroup implements WaitGroupInterface
{
    private string $groupUuid;

    private int $unixTimeout;

    /**
     * @var array<string, ServerTask>
     */
    private array $tasks;

    /**
     * @param array<int|string, Closure> $callbacks
     */
    public function __construct(
        private readonly Context $context,
        array &$callbacks,
        int $timeoutSeconds,
        private readonly int $workersLimit,
        private readonly WorkersRpcClient $rpcClient,
        private readonly ServerTaskTransport $serverTaskTransport,
        private readonly TaskResultTransport $taskResultTransport,
        private readonly EventsBusInterface $eventsBus,
        private readonly CallbackCallerInterface $callbackCaller,
    ) {
        $this->groupUuid = $this->makeUuid();

        $this->tasks = [];

        $this->unixTimeout = time() + $timeoutSeconds;

        $taskKeys = array_keys($callbacks);

        foreach ($taskKeys as $taskKey) {
            $this->tasks[$this->makeUuid()] = new ServerTask(
                context: $this->context,
                key: $taskKey,
                callback: $callbacks[$taskKey]
            );

            unset($callbacks[$taskKey]);
        }
    }

    public function get(): Generator
    {
        $workingTasksCount = 0;

        $freeTasks = $this->tasks;

        $expectedTasksCount = count($this->tasks);

        $workersLimit = ($this->workersLimit <= 0 || $this->workersLimit > $expectedTasksCount)
            ? $expectedTasksCount
            : $this->workersLimit;

        $groupUuid = $this->groupUuid;

        while (count($this->tasks) > 0) {
            $this->context->check();

            foreach ($freeTasks as $taskUuid => $task) {
                if (($workersLimit - $workingTasksCount) <= 0) {
                    break;
                }

                $this->context->check();

                try {
                    $this->rpcClient->addTask(
                        groupUuid: $groupUuid,
                        taskUuid: $taskUuid,
                        payload: $this->serverTaskTransport->serialize($task),
                        unixTimeout: $this->unixTimeout
                    );

                    unset($freeTasks[$taskUuid]);

                    ++$workingTasksCount;
                } catch (Throwable $exception) {
                    $this->eventsBus->onServerGone(
                        context: $this->context,
                        exception: new RpcCallException($exception)
                    );

                    foreach ($this->initSyncWaitGroup()->get() as $taskResult) {
                        yield $taskResult;
                    }

                    return;
                }
            }

            try {
                $finishedTask = $this->rpcClient->detectAnyFinishedTask(
                    groupUuid: $groupUuid
                );
            } catch (Throwable $exception) {
                $this->eventsBus->onServerGone(
                    context: $this->context,
                    exception: new RpcCallException($exception)
                );

                foreach ($this->initSyncWaitGroup()->get() as $taskResult) {
                    yield $taskResult;
                }

                return;
            }

            if (!$finishedTask->isFinished) {
                continue;
            }

            $finishedTaskUuid = $finishedTask->taskUuid;

            if (!array_key_exists($finishedTaskUuid, $this->tasks)) {
                throw new UnexpectedServerTaskException(
                    $finishedTaskUuid
                );
            }

            if ($this->isEncodedJson($finishedTask->response)) {
                try {
                    $result = $this->taskResultTransport->unserialize(
                        data: $finishedTask->response
                    );
                } catch (Throwable $exception) {
                    $result = new TaskResult(
                        taskKey: $this->tasks[$finishedTaskUuid]->key,
                        exception: $exception,
                        result: $finishedTask->response
                    );
                }
            } else {
                $result = new TaskResult(
                    taskKey: $this->tasks[$finishedTaskUuid]->key,
                    exception: new RuntimeException(
                        message: trim($finishedTask->response)
                    )
                );
            }

            unset($this->tasks[$finishedTaskUuid]);

            --$workingTasksCount;

            yield $result;
        }
    }

    public function cancel(): void
    {
        $this->tasks = [];

        try {
            $this->rpcClient->cancelGroup(
                groupUuid: $this->groupUuid
            );
        } catch (Throwable $exception) {
            $this->eventsBus->onServerGone(
                context: $this->context,
                exception: new RpcCallException($exception)
            );
        }
    }

    private function makeUuid(): string
    {
        return uniqid(more_entropy: true);
    }

    private function isEncodedJson(string $data): bool
    {
        json_decode($data);

        return json_last_error() === JSON_ERROR_NONE;
    }

    private function initSyncWaitGroup(): SyncWaitGroup
    {
        $callbacks = [];

        foreach (array_keys($this->tasks) as $taskKey) {
            $expectedTask = $this->tasks[$taskKey];

            $callbacks[$expectedTask->key] = $expectedTask->callback;

            unset($this->tasks[$taskKey]);
        }

        return new SyncWaitGroup(
            context: $this->context,
            callbacks: $callbacks,
            eventsBus: $this->eventsBus,
            callbackCaller: $this->callbackCaller
        );
    }

    public function __destruct()
    {
        try {
            $this->cancel();
        } catch (Throwable) {
            // no action
        }
    }
}
