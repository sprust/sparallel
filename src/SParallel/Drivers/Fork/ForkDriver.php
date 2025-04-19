<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use Closure;
use Generator;
use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Fork\Service\Task;
use SParallel\Drivers\Timer;
use SParallel\Objects\Context;
use SParallel\Objects\ResultObject;
use SParallel\Services\Fork\ForkHandler;
use SParallel\Services\Socket\SocketIO;
use SParallel\Transport\ResultTransport;

class ForkDriver implements DriverInterface
{
    public const DRIVER_NAME = 'fork';

    public function __construct(
        protected ResultTransport $resultTransport,
        protected ForkHandler $forkHandler,
        protected SocketIO $socketIO,
        protected ?Context $context = null,
        protected ?EventsBusInterface $eventsBus = null,
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

                $childClient = @socket_accept($task->socket);

                if ($childClient === false) {
                    if ($task->isFinished()) {
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
                        $response = $this->socketIO->readSocket(
                            timer: $timer,
                            socket: $childClient
                        );
                    } finally {
                        $this->socketIO->closeSocket($childClient);
                        $this->socketIO->closeSocket($task->socket);
                    }

                    unset($tasks[$key]);

                    yield $this->resultTransport->unserialize($response);
                }
            }
        }
    }

    protected function forkForTask(Timer $timer, mixed $key, Closure $callback): Task
    {
        $socketPath = $this->socketIO->makeSocketPath();

        $socket = $this->socketIO->createServer($socketPath);

        $childPid = $this->forkHandler->handle(
            timer: $timer,
            driverName: static::DRIVER_NAME,
            socketPath: $socketPath,
            key: $key,
            callback: $callback
        );

        return new Task(
            pid: $childPid,
            socket: $socket,
        );
    }
}
