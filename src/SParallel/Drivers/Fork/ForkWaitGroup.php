<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use Generator;
use RuntimeException;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Fork\Service\Task;
use SParallel\Drivers\Timer;
use SParallel\Objects\ResultObject;
use SParallel\Services\Fork\ForkService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\ResultTransport;

/**
 * TODO:
 * PROBLEM:
 * ForkService::isFinished return false when
 * try sleep (sleep, usleep) more than 1 sec inside callback
 */
class ForkWaitGroup implements WaitGroupInterface
{
    /**
     * @param array<int, Task> $tasks
     */
    public function __construct(
        protected array $tasks,
        protected Timer $timer,
        protected ResultTransport $resultTransport,
        protected SocketService $socketService,
        protected ForkService $forkService,
    ) {
    }

    public function get(): Generator
    {
        while (count($this->tasks) > 0) {
            $this->timer->check();

            $keys = array_keys($this->tasks);

            foreach ($keys as $key) {
                $this->timer->check();

                $task = $this->tasks[$key];

                $childClient = @socket_accept($task->socketServer->socket);

                if ($childClient === false) {
                    if ($this->forkService->isFinished($task->pid)) {
                        $this->socketService->closeSocketServer($task->socketServer);

                        unset($this->tasks[$key]);

                        yield new ResultObject(
                            key: $key,
                            exception: new RuntimeException(
                                "Unexpected error occurred while waiting for child process to finish. "
                            )
                        );
                    } else {
                        $this->timer->check();

                        usleep(1000);
                    }
                } else {
                    try {
                        $response = $this->socketService->readSocket(
                            timer: $this->timer,
                            socket: $childClient
                        );
                    } finally {
                        $this->socketService->closeSocketServer($task->socketServer);
                    }

                    unset($this->tasks[$key]);

                    yield $this->resultTransport->unserialize($response);
                }
            }
        }
    }

    public function break(): void
    {
        $keys = array_keys($this->tasks);

        foreach ($keys as $key) {
            $task = $this->tasks[$key];

            $this->socketService->closeSocketServer($task->socketServer);

            unset($this->tasks[$key]);
        }
    }

    public function __destruct()
    {
        $this->break();
    }
}
