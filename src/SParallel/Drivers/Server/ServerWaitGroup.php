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
use SParallel\Server\Workers\ServerTask;
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
    private array $expectedTasks;

    /**
     * @param array<int|string, Closure> $callbacks
     */
    public function __construct(
        private readonly Context $context,
        array &$callbacks,
        int $timeoutSeconds,
        private readonly WorkersRpcClient $rpcClient,
        private readonly ServerTaskTransport $serverTaskTransport,
        private readonly TaskResultTransport $taskResultTransport,
        private readonly EventsBusInterface $eventsBus,
        private readonly CallbackCallerInterface $callbackCaller,
    ) {
        $this->groupUuid = $this->makeUuid();

        $this->expectedTasks = [];

        $this->unixTimeout = time() + $timeoutSeconds;

        $taskKeys = array_keys($callbacks);

        foreach ($taskKeys as $taskKey) {
            $this->expectedTasks[$this->makeUuid()] = new ServerTask(
                context: $this->context,
                key: $taskKey,
                callback: $callbacks[$taskKey]
            );

            unset($callbacks[$taskKey]);
        }
    }

    public function get(): Generator
    {
        foreach ($this->expectedTasks as $taskUuid => $task) {
            $this->context->check();

            try {
                $this->rpcClient->addTask(
                    groupUuid: $this->groupUuid,
                    taskUuid: $taskUuid,
                    payload: $this->serverTaskTransport->serialize($task),
                    unixTimeout: $this->unixTimeout
                );
            } catch (Throwable $exception) {
                $this->eventsBus->onServerGone(
                    context: $this->context,
                    exception: new RpcCallException($exception)
                );

                foreach ($this->initSyncWaitGroup()->get() as $taskResult) {
                    yield $taskResult;
                }
            }
        }

        while (count($this->expectedTasks) > 0) {
            $this->context->check();

            try {
                $finishedTask = $this->rpcClient->detectAnyFinishedTask(
                    groupUuid: $this->groupUuid
                );
            } catch (Throwable $exception) {
                $this->eventsBus->onServerGone(
                    context: $this->context,
                    exception: new RpcCallException($exception)
                );

                foreach ($this->initSyncWaitGroup()->get() as $taskResult) {
                    dump($taskResult->taskKey);

                    yield $taskResult;
                }

                break;
            }

            if (!$finishedTask->isFinished) {
                continue;
            }

            if (!array_key_exists($finishedTask->taskUuid, $this->expectedTasks)) {
                throw new UnexpectedServerTaskException(
                    $finishedTask->taskUuid
                );
            }

            if ($this->isSerializedString($finishedTask->response)) {
                try {
                    $result = $this->taskResultTransport->unserialize(
                        data: $finishedTask->response
                    );
                } catch (Throwable $exception) {
                    $result = new TaskResult(
                        taskKey: $this->expectedTasks[$finishedTask->taskUuid]->key,
                        exception: $exception,
                        result: $finishedTask->response
                    );
                }
            } else {
                $result = new TaskResult(
                    taskKey: $this->expectedTasks[$finishedTask->taskUuid]->key,
                    exception: new RuntimeException(
                        message: trim($finishedTask->response)
                    )
                );
            }

            unset($this->expectedTasks[$finishedTask->taskUuid]);

            yield $result;
        }
    }

    public function cancel(): void
    {
        $this->expectedTasks = [];

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

    private function isSerializedString(string $data): bool
    {
        $pattern = '/^((s|i|d|b|a|O|C):|N;)/';

        if (!preg_match($pattern, $data)) {
            return false;
        }

        return true;
    }

    private function initSyncWaitGroup(): SyncWaitGroup
    {
        $callbacks = [];

        foreach (array_keys($this->expectedTasks) as $taskKey) {
            $expectedTask = $this->expectedTasks[$taskKey];

            $callbacks[$expectedTask->key] = $expectedTask->callback;

            unset($this->expectedTasks[$taskKey]);
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
