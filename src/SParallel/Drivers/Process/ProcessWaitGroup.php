<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use Generator;
use RuntimeException;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Timer;
use SParallel\Objects\Context;
use SParallel\Objects\ProcessParentMessage;
use SParallel\Objects\ProcessTask;
use SParallel\Objects\SocketServerObject;
use SParallel\Objects\TaskResult;
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
        protected SocketServerObject $socketServer,
        protected array $processTasks,
        protected Timer $timer,
        protected Context $context,
        protected SocketService $socketService,
        protected ContextTransport $contextTransport,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected EventsBusInterface $eventsBus,
        protected ProcessMessagesTransport $messageTransport,
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
                    throw new RuntimeException(
                        sprintf(
                            'Task key "%s" not found in processTask list',
                            $message->taskKey
                        )
                    );
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
                    throw new RuntimeException(
                        sprintf(
                            'Unknown operation "%s" for task key "%s"',
                            $message->operation,
                            $message->taskKey
                        )
                    );
                }
            }
        }

        while (true) {
            $processTask = $this->shiftProcessTask();

            if (!$processTask) {
                break;
            }

            $output = $this->readProcessOutput($processTask->process);

            $this->stopProcess($processTask->process);

            yield new TaskResult(
                key: $processTask->key,
                exception: new RuntimeException(
                    $output ?: "Unexpected process termination of task [$processTask->key]"
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

        $this->socketService->closeSocketServer($this->socketServer);
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

    protected function readProcessOutput(Process $process): ?string
    {
        if (!$process->isStarted()) {
            return null;
        }

        if ($output = $process->getOutput()) {
            $process->clearOutput();

            return trim($output);
        }

        if ($errorOutput = $process->getErrorOutput()) {
            $process->clearErrorOutput();

            return trim($errorOutput);
        }

        return null;
    }

    public function __destruct()
    {
        $this->break();
    }
}
