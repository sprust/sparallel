<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use Closure;
use Generator;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\ProcessCommandResolverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Exceptions\UnexpectedTaskException;
use SParallel\Exceptions\UnexpectedTaskOperationException;
use SParallel\Exceptions\UnexpectedTaskTerminationException;
use SParallel\Objects\ProcessParentMessage;
use SParallel\Objects\ProcessTask;
use SParallel\Objects\SocketServer;
use SParallel\Objects\TaskResult;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ProcessMessagesTransport;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessWaitGroup implements WaitGroupInterface
{
    protected string $command;

    /**
     * @var array<mixed, ProcessTask>
     */
    protected array $activeProcessTasks;

    /**
     * @var array<mixed>
     */
    protected array $remainTaskKeys;

    /**
     * @param array<mixed, Closure> $callbacks
     */
    public function __construct(
        protected array &$callbacks,
        protected int $workersLimit,
        protected SocketServer $socketServer,
        protected Context $context,
        protected ProcessCommandResolverInterface $processCommandResolver,
        protected SocketService $socketService,
        protected ContextTransport $contextTransport,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected EventsBusInterface $eventsBus,
        protected ProcessMessagesTransport $messageTransport,
        protected ProcessService $processService
    ) {
        $this->command = $this->processCommandResolver->get();

        $this->activeProcessTasks = [];

        $this->remainTaskKeys = array_keys($this->callbacks);

        $this->shiftWorkers();
    }

    public function get(): Generator
    {
        $serializedContext = $this->contextTransport->serialize($this->context);

        while (true) {
            $this->context->check();

            $this->shiftWorkers();

            if (!count($this->activeProcessTasks)) {
                break;
            }

            $currentActiveProcessTasks = $this->activeProcessTasks;

            $taskKeys = array_keys($currentActiveProcessTasks);

            foreach ($taskKeys as $taskKey) {
                $processTask = $this->activeProcessTasks[$taskKey];

                if (!$processTask->process->isRunning()) {
                    unset($this->activeProcessTasks[$taskKey]);
                }
            }

            while (true) {
                $childClient = @socket_accept($this->socketServer->socket);

                if ($childClient === false) {
                    usleep(1000);

                    break;
                }

                $response = $this->socketService->readSocket(
                    context: $this->context,
                    socket: $childClient
                );

                $message = $this->messageTransport->unserializeChild($response);

                $processTask = $currentActiveProcessTasks[$message->taskKey] ?? null;

                if (!$processTask) {
                    throw new UnexpectedTaskException($message->taskKey);
                }

                if ($message->operation === 'get') {
                    $this->socketService->writeToSocket(
                        context: $this->context,
                        socket: $childClient,
                        data: $this->messageTransport->serializeParent(
                            new ProcessParentMessage(
                                taskKey: $message->taskKey,
                                serializedContext: $serializedContext,
                                serializedCallback: $processTask->serializedCallback
                            )
                        )
                    );
                } elseif ($message->operation === 'resp') {
                    $result = $this->resultTransport->unserialize($message->payload);

                    $this->deleteRemainTaskKeys($result->taskKey);

                    $pid = $processTask->process->getPid();

                    try {
                        $processTask->process->stop();
                    } finally {
                        if ($pid) {
                            $this->eventsBus->processFinished($pid);
                        }
                    }

                    yield $result;
                } else {
                    throw new UnexpectedTaskOperationException(
                        taskKey: $message->taskKey,
                        operation: $message->operation,
                    );
                }
            }
        }

        while (true) {
            $processTask = $this->pullProcessTask();

            if (!$processTask) {
                break;
            }

            $this->deleteRemainTaskKeys($processTask->taskKey);

            $output = $this->processService->getOutput($processTask->process);

            $this->stopProcess($processTask);

            yield new TaskResult(
                taskKey: $processTask->taskKey,
                exception: new UnexpectedTaskTerminationException(
                    taskKey: $processTask->taskKey,
                    description: $output ?: null
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
            $processTask = $this->pullProcessTask();

            if (!$processTask) {
                break;
            }

            $this->stopProcess($processTask);
        }
    }

    protected function shiftWorkers(): void
    {
        $activeProcessTasksCount = count($this->activeProcessTasks);

        if ($activeProcessTasksCount >= $this->workersLimit) {
            return;
        }

        $taskKeys = array_slice(
            array: array_keys($this->callbacks),
            offset: 0,
            length: $this->workersLimit - $activeProcessTasksCount
        );

        foreach ($taskKeys as $taskKey) {
            $callback = $this->callbacks[$taskKey];

            $process = Process::fromShellCommandline(command: $this->command)
                ->setTimeout(null)
                ->setEnv([
                    ProcessDriver::PARAM_TASK_KEY => serialize($taskKey),
                    ProcessDriver::PARAM_SOCKET_PATH => $this->socketServer->path,
                ]);

            $process->start();

            $this->activeProcessTasks[$taskKey] = new ProcessTask(
                pid: $process->getPid(),
                taskKey: $taskKey,
                serializedCallback: $this->callbackTransport->serialize($callback),
                process: $process
            );

            unset($this->callbacks[$taskKey]);
        }
    }

    protected function pullProcessTask(): ?ProcessTask
    {
        return array_shift($this->activeProcessTasks);
    }

    protected function stopProcess(ProcessTask $processTask): void
    {
        if ($processTask->process->isRunning()) {
            try {
                $processTask->process->stop();
            } catch (Throwable) {
                //
            }
        }
    }

    protected function deleteRemainTaskKeys(mixed $taskKey): void
    {
        $this->remainTaskKeys = array_filter(
            $this->remainTaskKeys,
            static fn(mixed $remainTaskKey) => $remainTaskKey !== $taskKey
        );
    }

    public function __destruct()
    {
        $this->break();
    }
}
