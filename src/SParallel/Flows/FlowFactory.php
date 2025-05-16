<?php

declare(strict_types=1);

namespace SParallel\Flows;

use Closure;
use Psr\Log\LoggerInterface;
use SParallel\Contracts\DriverFactoryInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\FlowInterface;
use SParallel\Contracts\FlowTypeResolverInterface;
use SParallel\Flows\ASync\ASyncFlow;
use SParallel\Flows\Sync\SyncFlow;
use SParallel\Services\Callback\CallbackCaller;
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
        protected LoggerInterface $logger,
        protected FlowTypeResolverInterface $flowTypeResolver,
        protected EventsBusInterface $eventsBus,
        protected CallbackCaller $callbackCaller,
    ) {
    }

    /**
     * @param array<int|string, Closure> $callbacks
     */
    public function create(array &$callbacks, Context $context, int $workersLimit): FlowInterface
    {
        if ($this->flowTypeResolver->isAsync()) {
            $flow = new ASyncFlow(
                contextTransport: $this->contextTransport,
                callbackTransport: $this->callbackTransport,
                resultTransport: $this->resultTransport,
                messageTransport: $this->messageTransport,
                logger: $this->logger,
            );
        } else {
            $flow = new SyncFlow(
                eventsBus: $this->eventsBus,
                callbackCaller: $this->callbackCaller,
            );
        }

        return $flow->start(
            context: $context,
            driver: $this->driverFactory->detect(),
            callbacks: $callbacks,
            workersLimit: $workersLimit,
        );
    }
}
