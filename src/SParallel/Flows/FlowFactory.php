<?php

declare(strict_types=1);

namespace SParallel\Flows;

use Closure;
use Psr\Log\LoggerInterface;
use SParallel\Contracts\FlowInterface;
use SParallel\Contracts\DriverFactoryInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Entities\SocketServer;
use SParallel\Flows\ASync\ASyncFlow;
use SParallel\Services\Context;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\MessageTransport;
use SParallel\Transport\ResultTransport;

readonly class FlowFactory
{
    public function __construct(
        protected SocketService $socketService,
        protected DriverFactoryInterface $driverFactory,
        protected ContextTransport $contextTransport,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected MessageTransport $messageTransport,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * @param array<int|string, Closure> $callbacks
     */
    public function create(array &$callbacks, Context $context, int $workersLimit): FlowInterface
    {
        $flow = new ASyncFlow(
            socketService: $this->socketService,
            contextTransport: $this->contextTransport,
            callbackTransport: $this->callbackTransport,
            resultTransport: $this->resultTransport,
            messageTransport: $this->messageTransport,
            logger: $this->logger,
        );

        return $flow->start(
            context: $context,
            driver: $this->driverFactory->detect(),
            callbacks: $callbacks,
            workersLimit: $workersLimit,
            socketServer: $this->socketService->createServer(
                socketPath: $this->socketService->makeSocketPath()
            )
        );
    }
}
