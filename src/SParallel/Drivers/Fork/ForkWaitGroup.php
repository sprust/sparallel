<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use Generator;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\UnexpectedTaskTerminationException;
use SParallel\Objects\TaskResult;
use SParallel\Objects\SocketServerObject;
use SParallel\Services\Fork\ForkService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\ResultTransport;

class ForkWaitGroup implements WaitGroupInterface
{
    /**
     * @param array<mixed, int> $childProcessIds
     */
    public function __construct(
        protected SocketServerObject $socketServer,
        protected array $childProcessIds,
        protected Timer $timer,
        protected ResultTransport $resultTransport,
        protected SocketService $socketService,
        protected ForkService $forkService,
    ) {
    }

    public function get(): Generator
    {
        $remainTaskKeys = array_keys($this->childProcessIds);

        $socketServer = $this->socketServer->socket;

        $childrenFinished = false;

        while (!$childrenFinished) {
            $taskKeys = array_keys($this->childProcessIds);

            foreach ($taskKeys as $taskKey) {
                $childProcessPid = $this->childProcessIds[$taskKey];

                if ($this->forkService->isFinished($childProcessPid)) {
                    unset($this->childProcessIds[$taskKey]);
                }
            }

            $childClient = @socket_accept($socketServer);

            if ($childClient === false) {
                if (!count($this->childProcessIds)) {
                    $childrenFinished = true;
                } else {
                    $this->timer->check();

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
                exception: new UnexpectedTaskTerminationException(
                    taskKey: $taskKey
                )
            );
        }

        $this->break();
    }

    public function break(): void
    {
        $keys = array_keys($this->childProcessIds);

        foreach ($keys as $key) {
            $pid = $this->childProcessIds[$key];

            if (!$this->forkService->isFinished($pid)) {
                posix_kill($pid, SIGKILL);
            }

            unset($this->childProcessIds[$key]);
        }

        // should be closed in finally block
        $this->socketService->closeSocketServer($this->socketServer);
    }

    public function __destruct()
    {
        $this->break();
    }
}
