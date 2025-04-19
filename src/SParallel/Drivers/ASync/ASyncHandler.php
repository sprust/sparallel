<?php

declare(strict_types=1);

namespace SParallel\Drivers\ASync;

use Psr\Container\ContainerInterface;
use RuntimeException;
use SParallel\Drivers\Timer;
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
        $socketPath = $_SERVER[ASyncDriver::PARAM_SOCKET_PATH] ?? null;

        if (!$socketPath) {
            throw new RuntimeException('Socket path is not set.');
        }

        $timer = new Timer(
            timeoutSeconds: (int) $_SERVER[ASyncDriver::PARAM_TIMER_TIMEOUT_SECONDS],
            customStartTime: (int) $_SERVER[ASyncDriver::PARAM_TIMER_START_TIME],
        );

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

        $context = $this->contextTransport->unserialize($responseData['sc']);

        $this->container->set(Context::class, static fn() => $context);

        /** @var array<mixed, int> $childProcessIds */
        $childProcessIds = [];

        foreach ($responseData['pl'] as $key => $serializedCallback) {
            $childProcessIds[$key] = $this->forkHandler->handle(
                timer: $timer,
                driverName: ASyncDriver::DRIVER_NAME,
                socketPath: $serializedCallback['sp'],
                key: $key,
                callback: $this->callbackTransport->unserialize($serializedCallback['cb'])
            );
        }

        // TODO: see in fork driver
        while (count($childProcessIds) > 0) {
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
