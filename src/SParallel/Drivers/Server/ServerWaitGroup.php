<?php

declare(strict_types=1);

namespace SParallel\Drivers\Server;

use Closure;
use Generator;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Server\Rpc\ServerRpcClient;
use SParallel\Drivers\Server\Rpc\ServerTask;
use SParallel\Exceptions\UnexpectedServerTaskException;
use SParallel\Services\Context;
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
        private readonly ServerRpcClient $rpcClient,
        private readonly ServerTaskTransport $serverTaskTransport,
        private readonly TaskResultTransport $taskResultTransport,
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

            $this->rpcClient->addTask(
                groupUuid: $this->groupUuid,
                taskUuid: $taskUuid,
                payload: $this->serverTaskTransport->serialize($task),
                unixTimeout: $this->unixTimeout
            );
        }

        while (count($this->expectedTasks) > 0) {
            $this->context->check();

            $finishedTask = $this->rpcClient->detectAnyFinishedTask(
                groupUuid: $this->groupUuid
            );

            if (!$finishedTask->isFinished) {
                continue;
            }

            if (!array_key_exists($finishedTask->taskUuid, $this->expectedTasks)) {
                throw new UnexpectedServerTaskException(
                    $finishedTask->taskUuid
                );
            }

            unset($this->expectedTasks[$finishedTask->taskUuid]);

            yield $this->taskResultTransport->unserialize(
                data: $finishedTask->response
            );
        }
    }

    public function cancel(): void
    {
        $this->expectedTasks = [];

        $this->rpcClient->cancelGroup(
            groupUuid: $this->groupUuid
        );
    }

    private function makeUuid(): string
    {
        return uniqid(more_entropy: true);
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
