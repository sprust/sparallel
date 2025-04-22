<?php

declare(strict_types=1);

namespace SParallel\Drivers\ASync;

use Generator;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\SParallelTimeoutException;
use SParallel\Exceptions\UnexpectedTaskTerminationException;
use SParallel\Objects\TaskResult;
use SParallel\Objects\SocketServerObject;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\Process;
use Throwable;

class AsyncWaitGroup implements WaitGroupInterface
{
    /**
     * @var array<int, mixed> $taskKeys
     */
    public function __construct(
        protected array $taskKeys,
        protected Process $process,
        protected SocketServerObject $processSocketServer,
        protected Timer $timer,
        protected EventsBusInterface $eventsBus,
        protected SocketService $socketService,
        protected ResultTransport $resultTransport,
    ) {
    }

    public function get(): Generator
    {
        $remainTaskKeys = $this->taskKeys;

        $socketServer = $this->processSocketServer->socket;

        $processFinished = false;

        while (!$processFinished) {
            $childClient = @socket_accept($socketServer);

            if ($childClient === false) {
                if (!$this->process->isRunning()) {
                    $this->break();

                    $processFinished = true;
                } else {
                    try {
                        $this->timer->check();
                    } catch (SParallelTimeoutException $exception) {
                        $this->break();

                        throw $exception;
                    }

                    usleep(1000);
                }
            } else {
                $response = $this->socketService->readSocket(
                    timer: $this->timer,
                    socket: $childClient
                );

                $result = $this->resultTransport->unserialize($response);

                $remainTaskKeys = array_filter(
                    $remainTaskKeys,
                    static fn($key) => $key !== $result->key
                );

                yield $result;
            }
        }

        while (count($remainTaskKeys) > 0) {
            $taskKey = array_shift($remainTaskKeys);

            yield new TaskResult(
                key: $taskKey,
                exception: new UnexpectedTaskTerminationException($taskKey)
            );
        }
    }

    public function break(): void
    {
        try {
            $this->socketService->closeSocketServer($this->processSocketServer);
        } catch (Throwable) {
            //
        }

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
