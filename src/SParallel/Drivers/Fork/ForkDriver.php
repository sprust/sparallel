<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Services\Context;
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

    public function run(array &$callbacks, Context $context, int $workersLimit): WaitGroupInterface
    {
        $socketServer = $this->socketService->createServer(
            $this->socketService->makeSocketPath()
        );

        return new ForkWaitGroup(
            callbacks: $callbacks,
            workersLimit: $workersLimit,
            socketServer: $socketServer,
            context: $context,
            forkHandler: $this->forkHandler,
            resultTransport: $this->resultTransport,
            socketService: $this->socketService,
            forkService: $this->forkService
        );
    }
}
