<?php

declare(strict_types=1);

namespace SParallel\Drivers\Hybrid;

use SParallel\Contracts\ContextSetterInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\InvalidValueException;
use SParallel\Exceptions\SParallelTimeoutException;
use SParallel\Services\Fork\ForkHandler;
use SParallel\Services\Fork\ForkService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ResultTransport;

class HybridProcessHandler
{
    public function __construct(
        protected ContextSetterInterface $contextSetter,
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
     * @throws SParallelTimeoutException
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
     * @throws SParallelTimeoutException
     */
    protected function onHandle(): void
    {
        $socketPath     = $_SERVER[HybridDriver::PARAM_SOCKET_PATH] ?? null;
        $timeoutSeconds = $_SERVER[HybridDriver::PARAM_TIMER_TIMEOUT_SECONDS] ?? null;
        $startTime      = $_SERVER[HybridDriver::PARAM_TIMER_START_TIME] ?? null;

        if (!$socketPath || !is_string($socketPath)) {
            throw new InvalidValueException(
                'Socket path is not set or is not a string.'
            );
        }

        if (!$timeoutSeconds || !is_numeric($timeoutSeconds) || $timeoutSeconds < 0) {
            throw new InvalidValueException(
                'Timeout seconds is not set or is not numeric.'
            );
        }

        if (!$startTime || !is_numeric($startTime) || $startTime < 0) {
            throw new InvalidValueException(
                'Start time is not set or is not numeric.'
            );
        }

        $timer = new Timer(
            timeoutSeconds: (int) $timeoutSeconds,
            customStartTime: (int) $startTime,
        );

        // read payload from caller

        $socketClient = $this->socketService->createClient($socketPath);

        $response = $this->socketService->readSocket(
            timer: $timer,
            socket: $socketClient->socket
        );

        $responseData = json_decode($response, true);

        $context = $this->contextTransport->unserialize($responseData['c']);

        $this->contextSetter->set($context);

        /** @var array<mixed, int> $childProcessIds */
        $childProcessIds = [];

        foreach ($responseData['cb'] as $taskKey => $serializedCallback) {
            $childProcessIds[$taskKey] = $this->forkHandler->handle(
                timer: $timer,
                driverName: HybridDriver::DRIVER_NAME,
                socketPath: $socketPath,
                taskKey: $taskKey,
                callback: $this->callbackTransport->unserialize($serializedCallback)
            );
        }

        while (count($childProcessIds) > 0) {
            $timer->check();

            $childProcessIdKeys = array_keys($childProcessIds);

            foreach ($childProcessIdKeys as $childProcessIdKey) {
                $childProcessPid = $childProcessIds[$childProcessIdKey];

                if ($this->forkService->isFinished($childProcessPid)) {
                    unset($childProcessIds[$childProcessIdKey]);
                }
            }

            usleep(1000);
        }
    }
}
