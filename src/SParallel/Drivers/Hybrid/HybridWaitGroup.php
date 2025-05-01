<?php

declare(strict_types=1);

namespace SParallel\Drivers\Hybrid;

use Generator;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\UnexpectedTaskTerminationException;
use SParallel\Objects\SocketServer;
use SParallel\Objects\TaskResult;
use SParallel\Services\Context;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\Process;

class HybridWaitGroup implements WaitGroupInterface
{
    /**
     * @param array<int, mixed> $taskKeys
     */
    public function __construct(
        protected array $taskKeys,
        protected Process $process,
        protected SocketServer $socketServer,
        protected Context $context,
        protected EventsBusInterface $eventsBus,
        protected SocketService $socketService,
        protected ResultTransport $resultTransport,
    ) {
    }

    public function get(): Generator
    {
        $remainTaskKeys = $this->taskKeys;

        while (true) {
            try {
                $this->context->check();
            } catch (ContextCheckerException $exception) {
                $this->break();

                throw $exception;
            }

            $childClient = @socket_accept($this->socketServer->socket);

            if ($childClient === false) {
                if (!$this->process->isRunning()) {
                    $this->break();

                    break;
                } else {
                    usleep(1000);
                }
            } else {
                $response = $this->socketService->readSocket(
                    context: $this->context,
                    socket: $childClient
                );

                $result = $this->resultTransport->unserialize($response);

                $remainTaskKeys = array_filter(
                    $remainTaskKeys,
                    static fn($taskKey) => $taskKey !== $result->taskKey
                );

                yield $result;
            }
        }

        while (count($remainTaskKeys) > 0) {
            $taskKey = array_shift($remainTaskKeys);

            yield new TaskResult(
                taskKey: $taskKey,
                exception: new UnexpectedTaskTerminationException($taskKey)
            );
        }
    }

    public function break(): void
    {
        if ($this->process->isRunning()) {
            $this->process->stop();
        }

        if ($pid = $this->process->getPid()) {
            $this->eventsBus->processFinished(pid: $pid);
        }
    }

    public function __destruct()
    {
        $this->break();
    }
}
