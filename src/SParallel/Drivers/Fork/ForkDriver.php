<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Services\Canceler;
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

    public function run(array &$callbacks, Canceler $canceler): WaitGroupInterface
    {
        /** @var array<mixed, int> $childProcessIds */
        $childProcessIds = [];

        $socketPath = $this->socketService->makeSocketPath();

        $socketServer = $this->socketService->createServer($socketPath);

        $taskKeys = array_keys($callbacks);

        foreach ($taskKeys as $taskKey) {
            $callback = $callbacks[$taskKey];

            $childProcessIds[$taskKey] = $this->forkHandler->handle(
                canceler: $canceler,
                driverName: static::DRIVER_NAME,
                socketPath: $socketPath,
                taskKey: $taskKey,
                callback: $callback
            );

            unset($callbacks[$taskKey]);
        }

        return new ForkWaitGroup(
            socketServer: $socketServer,
            childProcessIds: $childProcessIds,
            canceler: $canceler,
            resultTransport: $this->resultTransport,
            socketService: $this->socketService,
            forkService: $this->forkService,
        );
    }
}
