<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use Closure;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Fork\Service\Task;
use SParallel\Drivers\Timer;
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

    public function run(array &$callbacks, Timer $timer): WaitGroupInterface
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

        return new ForkWaitGroup(
            tasks: $tasks,
            timer: $timer,
            resultTransport: $this->resultTransport,
            socketService: $this->socketService,
            forkService: $this->forkService,
        );
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
