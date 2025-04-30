<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\ProcessCommandResolverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ProcessMessagesTransport;
use SParallel\Transport\ResultTransport;

class ProcessDriver implements DriverInterface
{
    public const DRIVER_NAME = 'process';

    public const PARAM_TASK_KEY    = 'SPARALLEL_TASK_KEY';
    public const PARAM_SOCKET_PATH = 'SPARALLEL_SOCKET_PATH';

    public function __construct(
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected ContextTransport $contextTransport,
        protected SocketService $socketService,
        protected ProcessCommandResolverInterface $processCommandResolver,
        protected EventsBusInterface $eventsBus,
        protected ProcessMessagesTransport $messageTransport,
        protected ProcessService $processService,
    ) {
    }

    public function run(array &$callbacks, Context $context, int $workersLimit): WaitGroupInterface
    {
        $socketServer = $this->socketService->createServer(
            $this->socketService->makeSocketPath()
        );

        return new ProcessWaitGroup(
            callbacks: $callbacks,
            workersLimit: $workersLimit,
            socketServer: $socketServer,
            context: $context,
            processCommandResolver: $this->processCommandResolver,
            socketService: $this->socketService,
            contextTransport: $this->contextTransport,
            callbackTransport: $this->callbackTransport,
            resultTransport: $this->resultTransport,
            eventsBus: $this->eventsBus,
            messageTransport: $this->messageTransport,
            processService: $this->processService
        );
    }
}
