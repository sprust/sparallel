<?php

declare(strict_types=1);

namespace SParallel\Drivers\Hybrid;

use SParallel\Contracts\ContextResolverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\CancelerException;
use SParallel\Exceptions\InvalidValueException;
use SParallel\Services\Canceler;
use SParallel\Services\Fork\ForkHandler;
use SParallel\Services\Fork\ForkService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\CancelerTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ResultTransport;

class HybridProcessHandler
{
    public function __construct(
        protected ContextResolverInterface $contextResolver,
        protected ContextTransport $contextTransport,
        protected CancelerTransport $cancelerTransport,
        protected EventsBusInterface $eventsBus,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected SocketService $socketService,
        protected ForkHandler $forkHandler,
        protected ForkService $forkService,
    ) {
    }

    /**
     * @throws CancelerException
     */
    public function handle(): void
    {
        $pid = getmypid();

        $this->eventsBus->processCreated(pid: $pid);

        try {
            $this->onHandle();
        } finally {
            $this->eventsBus->processFinished(pid: $pid);
        }
    }

    /**
     * @throws CancelerException
     */
    protected function onHandle(): void
    {
        $socketPath = $_SERVER[HybridDriver::PARAM_SOCKET_PATH] ?? null;

        if (!$socketPath || !is_string($socketPath)) {
            throw new InvalidValueException(
                'Socket path is not set or is not a string.'
            );
        }

        $workersLimit = $_SERVER[HybridDriver::PARAM_WORKERS_LIMIT] ?? null;

        if (is_null($workersLimit)) {
            throw new InvalidValueException(
                'Workers limit is not set.'
            );
        }

        if (!is_numeric($workersLimit)) {
            throw new InvalidValueException(
                'Workers limit is not a number.'
            );
        }

        $workersLimit = (int) $workersLimit;

        $socketClient = $this->socketService->createClient($socketPath);

        $response = $this->socketService->readSocket(
            canceler: (new Canceler())->add(new Timer(timeoutSeconds: 2)),
            socket: $socketClient->socket
        );

        $responseData = json_decode($response, true);

        $context  = $this->contextTransport->unserialize($responseData['ctx']);
        $canceler = $this->cancelerTransport->unserialize($responseData['can']);

        $this->contextResolver->set($context);

        $forksExecutor = new HybridProcessForksExecutor(
            serializedCallbacks: $responseData['cbs'],
            workersLimit: $workersLimit,
            socketPath: $socketPath,
            canceler: $canceler,
            forkHandler: $this->forkHandler,
            callbackTransport: $this->callbackTransport,
            resultTransport: $this->resultTransport,
            socketService: $this->socketService,
            forkService: $this->forkService
        );

        $forksExecutor->exec();
    }
}
