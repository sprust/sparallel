<?php

declare(strict_types=1);

namespace SParallel\Drivers\ASync;

use Psr\Container\ContainerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\InvalidValueException;
use SParallel\Exceptions\SParallelTimeoutException;
use SParallel\Objects\Context;
use SParallel\Services\Fork\ForkHandler;
use SParallel\Services\Fork\ForkService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ResultTransport;

class ASyncHandler
{
    public function __construct(
        protected ContainerInterface $container,
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
        $socketPath     = $_SERVER[ASyncDriver::PARAM_SOCKET_PATH] ?? null;
        $timeoutSeconds = $_SERVER[ASyncDriver::PARAM_TIMER_TIMEOUT_SECONDS] ?? null;
        $startTime      = $_SERVER[ASyncDriver::PARAM_TIMER_START_TIME] ?? null;

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

        $socket = $this->socketService->createClient($socketPath);

        try {
            $response = $this->socketService->readSocket(
                timer: $timer,
                socket: $socket
            );
        } finally {
            $this->socketService->closeSocket($socket);
        }

        $responseData = json_decode($response, true);

        $context = $this->contextTransport->unserialize($responseData['c']);

        $this->container->set(Context::class, static fn() => $context);

        /** @var array<mixed, int> $childProcessIds */
        $childProcessIds = [];

        foreach ($responseData['cb'] as $key => $serializedCallback) {
            $childProcessIds[$key] = $this->forkHandler->handle(
                timer: $timer,
                driverName: ASyncDriver::DRIVER_NAME,
                socketPath: $socketPath,
                taskKey: $key,
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
