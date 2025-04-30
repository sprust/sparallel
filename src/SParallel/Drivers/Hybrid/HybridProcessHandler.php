<?php

declare(strict_types=1);

namespace SParallel\Drivers\Hybrid;

use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\InvalidValueException;
use SParallel\Services\Context;
use SParallel\Services\Fork\ForkHandler;
use SParallel\Services\Fork\ForkService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ResultTransport;

class HybridProcessHandler
{
    public function __construct(
        protected ContextTransport $contextTransport,
        protected EventsBusInterface $eventsBus,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected SocketService $socketService,
        protected ForkHandler $forkHandler,
        protected ForkService $forkService,
    ) {
    }

    /**
     * @throws ContextCheckerException
     */
    public function handle(): void
    {
        $pid = getmypid();

        var_dump($pid);

        $this->eventsBus->processCreated(pid: $pid);

        try {
            $this->onHandle();
        } finally {
            $this->eventsBus->processFinished(pid: $pid);
        }
    }

    /**
     * @throws ContextCheckerException
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

        $initContext = new Context();

        $response = $this->socketService->readSocket(
            context: $initContext->addChecker(new Timer(timeoutSeconds: 2)),
            socket: $socketClient->socket
        );

        $responseData = json_decode($response, true);

        $context = $this->contextTransport->unserialize($responseData['ctx']);

        $forksExecutor = new HybridProcessForksExecutor(
            serializedCallbacks: $responseData['cbs'],
            workersLimit: $workersLimit,
            socketPath: $socketPath,
            context: $context,
            forkHandler: $this->forkHandler,
            callbackTransport: $this->callbackTransport,
            resultTransport: $this->resultTransport,
            socketService: $this->socketService,
            forkService: $this->forkService
        );

        $forksExecutor->exec();
    }
}
