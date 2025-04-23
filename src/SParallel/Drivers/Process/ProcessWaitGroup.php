<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use Generator;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\UnexpectedTaskException;
use SParallel\Exceptions\UnexpectedTaskOperationException;
use SParallel\Exceptions\UnexpectedTaskTerminationException;
use SParallel\Objects\Context;
use SParallel\Objects\ProcessParentMessage;
use SParallel\Objects\ProcessTask;
use SParallel\Objects\SocketServer;
use SParallel\Objects\TaskResult;
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
    /**
     * @param array<mixed, ProcessTask> $processTasks
     */
    public function __construct(
        protected SocketServer $socketServer,
        protected array $processTasks,
        protected Timer $timer,
        protected Context $context,
        protected SocketService $socketService,
        protected ContextTransport $contextTransport,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected EventsBusInterface $eventsBus,
        protected ProcessMessagesTransport $messageTransport,
        protected ProcessService $processService
    ) {
    }

    public function get(): Generator
    {
        $serializedContext = $this->contextTransport->serialize($this->context);

        $socket = $this->socketServer->socket;

        while (count($this->processTasks) > 0) {
            $hasRunningProcesses = false;

            foreach ($this->processTasks as $processTask) {
                if ($processTask->process->isRunning()) {
                    $hasRunningProcesses = true;

                    break;
                }
            }

            $childClient = @socket_accept($socket);

            if ($childClient === false) {
                $this->timer->check();

                if (!$hasRunningProcesses) {
                    break;
                }

                usleep(1000);
            } else {
                $response = $this->socketService->readSocket(
                    timer: $this->timer,
                    socket: $childClient
                );

                $message = $this->messageTransport->unserializeChild($response);

                $processTask = $this->processTasks[$message->taskKey] ?? null;

                if (!$processTask) {
                    throw new UnexpectedTaskException($message->taskKey);
                }

                if ($message->operation === 'get') {
                    $this->socketService->writeToSocket(
                        timer: $this->timer,
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

                    unset($this->processTasks[$message->taskKey]);

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
            $processTask = $this->shiftProcessTask();

            if (!$processTask) {
                break;
            }

            $output = $this->processService->getOutput($processTask->process);

            $this->stopProcess($processTask->process);

            yield new TaskResult(
                taskKey: $processTask->taskKey,
                exception: new UnexpectedTaskTerminationException(
                    taskKey: $processTask->taskKey,
                    description: $output ?: null
                )
            );
        }
    }

    public function break(): void
    {
        while (true) {
            $processTask = $this->shiftProcessTask();

            if (!$processTask) {
                break;
            }

            $this->stopProcess($processTask->process);
        }
    }

    protected function shiftProcessTask(): ?ProcessTask
    {
        return array_shift($this->processTasks);
    }

    protected function stopProcess(Process $process): void
    {
        if ($process->isRunning()) {
            try {
                $process->stop();
            } catch (Throwable) {
                //
            }
        }

        if ($pid = $process->getPid()) {
            $this->eventsBus->processFinished($pid);
        }
    }

    public function __destruct()
    {
        $this->break();
    }
}
