<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use Closure;
use Generator;
use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Drivers\Fork\Service\Task;
use SParallel\Drivers\Timer;
use SParallel\Objects\ResultObject;
use SParallel\Services\Fork\ForkHandler;
use SParallel\Services\Fork\ForkService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\ResultTransport;

class ForkDriver implements DriverInterface
{
    public const DRIVER_NAME = 'fork';

    public function __construct(
        protected ResultTransport $resultTransport,
        protected ForkHandler $forkHandler,
        protected SocketService $socketService,
        protected ForkService $forkService,
    ) {
    }

    public function run(array &$callbacks, Timer $timer): Generator
    {
        /** @var array<mixed, Task> $tasks */
        $tasks = [];

        $callbackKeys = array_keys($callbacks);

        foreach ($callbackKeys as $callbackKey) {
            $callback = $callbacks[$callbackKey];

            $tasks[$callbackKey] = $this->forkForTask(
                timer: $timer,
                key: $callbackKey,
                callback: $callback
            );

            unset($callbacks[$callbackKey]);
        }

        while (count($tasks) > 0) {
            $timer->check();

            $keys = array_keys($tasks);

            foreach ($keys as $key) {
                $timer->check();

                $task = $tasks[$key];

                $childClient = @socket_accept($task->socketServer->socket);

                if ($childClient === false) {
                    if ($this->forkService->isFinished($task->pid)) {
                        $this->socketService->closeSocketServer($task->socketServer);

                        unset($tasks[$key]);

                        yield new ResultObject(
                            key: $key,
                            exception: new RuntimeException(
                                "Unexpected error occurred while waiting for child process to finish. "
                            )
                        );
                    } else {
                        $timer->check();

                        usleep(1000);
                    }
                } else {
                    try {
                        $response = $this->socketService->readSocket(
                            timer: $timer,
                            socket: $childClient
                        );
                    } finally {
                        $this->socketService->closeSocketServer($task->socketServer);
                    }

                    unset($tasks[$key]);

                    yield $this->resultTransport->unserialize($response);
                }
            }
        }
    }

    protected function forkForTask(Timer $timer, mixed $key, Closure $callback): Task
    {
        $socketPath = $this->socketService->makeSocketPath();

        $socketServer = $this->socketService->createServer($socketPath);

        $childPid = $this->forkHandler->handle(
            timer: $timer,
            driverName: static::DRIVER_NAME,
            socketPath: $socketPath,
            key: $key,
            callback: $callback
        );

        return new Task(
            pid: $childPid,
            socketServer: $socketServer,
        );
    }
}
