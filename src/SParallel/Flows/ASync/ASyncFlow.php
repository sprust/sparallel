<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync;

use Generator;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\FlowInterface;
use SParallel\Contracts\TaskInterface;
use SParallel\Contracts\TaskManagerInterface;
use SParallel\Entities\SocketServer;
use SParallel\Enum\MessageOperationTypeEnum;
use SParallel\Exceptions\UnexpectedTaskException;
use SParallel\Exceptions\UnexpectedTaskOperationException;
use SParallel\Exceptions\UnexpectedTaskTerminationException;
use SParallel\Objects\Message;
use SParallel\Objects\TaskResult;
use SParallel\Services\Context;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\MessageTransport;
use SParallel\Transport\ResultTransport;

class ASyncFlow implements FlowInterface
{
    protected Context $context;
    protected array $callbacks;
    protected int $workersLimit;
    protected TaskManagerInterface $taskManager;

    protected SocketServer $socketServer;

    /**
     * @var array<int|string, TaskInterface>
     */
    protected array $activeTasks;

    /**
     * @var array<int|string>
     */
    protected array $remainTaskKeys;

    public function __construct(
        protected SocketService $socketService,
        protected ContextTransport $contextTransport,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected EventsBusInterface $eventsBus,
        protected MessageTransport $messageTransport,
    ) {
    }

    /**
     * @param array<int|string, callable> $callbacks
     */
    public function start(
        Context $context,
        TaskManagerInterface $taskManager,
        array &$callbacks,
        int $workersLimit,
        SocketServer $socketServer
    ): static {
        $this->context      = $context;
        $this->callbacks    = $callbacks;
        $this->workersLimit = $workersLimit;
        $this->taskManager  = $taskManager;

        $this->socketServer = $socketServer;

        $this->activeTasks = [];

        $this->remainTaskKeys = array_keys($this->callbacks);

        $this->taskManager->init(
            context: $context,
            callbacks: $callbacks,
            workersLimit: $workersLimit,
            socketServer: $socketServer
        );

        $this->shiftWorkers();

        return $this;
    }

    public function get(): Generator
    {
        $serializedContext = $this->contextTransport->serialize($this->context);

        while (true) {
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
                    unset($this->activeTasks[$taskKey]);
                }
            }

            while (true) {
                $childClient = $this->socketService->accept($this->socketServer->socket);

                if ($childClient === false) {
                    $this->context->check();

                    usleep(1000);

                    break;
                }

                $response = $this->socketService->readSocket(
                    context: $this->context,
                    socket: $childClient
                );

                $message = $this->messageTransport->unserialize($response);

                $task = $currentActiveTasks[$message->taskKey] ?? null;

                if (!$task) {
                    throw new UnexpectedTaskException($message->taskKey);
                }

                if ($message->operation === MessageOperationTypeEnum::GetJob) {
                    $this->socketService->writeToSocket(
                        context: $this->context,
                        socket: $childClient,
                        data: $this->messageTransport->serialize(
                            new Message(
                                operation: MessageOperationTypeEnum::Job,
                                taskKey: $message->taskKey,
                                serializedContext: $serializedContext,
                                payload: $this->callbackTransport->serialize(
                                    callback: $task->getCallback()
                                )
                            )
                        )
                    );
                } elseif ($message->operation === MessageOperationTypeEnum::Response) {
                    $result = $this->resultTransport->unserialize($message->payload);

                    $this->deleteRemainTaskKeys($result->taskKey);

                    $pid = $task->getPid();

                    try {
                        $task->finish();
                    } finally {
                        $this->eventsBus->processFinished($pid);
                    }

                    yield $result;
                } else {
                    throw new UnexpectedTaskOperationException(
                        taskKey: $message->taskKey,
                        operation: $message->operation->value,
                    );
                }
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
        while (true) {
            $task = $this->pullTask();

            if (!$task) {
                break;
            }

            $task->finish();
        }
    }

    protected function shiftWorkers(): void
    {
        $activeTasksCount = count($this->activeTasks);

        if ($activeTasksCount >= $this->workersLimit) {
            return;
        }

        $taskKeys = array_slice(
            array: array_keys($this->callbacks),
            offset: 0,
            length: $this->workersLimit - $activeTasksCount
        );

        foreach ($taskKeys as $taskKey) {
            $callback = $this->callbacks[$taskKey];

            $task = $this->taskManager->create(
                context: $this->context,
                socketServer: $this->socketServer,
                key: $taskKey,
                callback: $callback
            );

            $this->eventsBus->processCreated($task->getPid());

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
