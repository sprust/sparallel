<?php

declare(strict_types=1);

namespace SParallel\Drivers\ASync;

use Psr\Container\ContainerInterface;
use RuntimeException;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\SParallelTimeoutException;
use SParallel\Objects\Context;
use SParallel\Services\Fork\ForkHandler;
use SParallel\Services\Socket\SocketIO;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ResultTransport;

class ASyncProcess
{
    public function __construct(
        protected ContainerInterface $container,
        protected ContextTransport $contextTransport,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected SocketIO $socketIO,
        protected ForkHandler $forkHandler,
        protected ?EventsBusInterface $eventsBus,
    ) {
    }

    /**
     * @throws SParallelTimeoutException
     */
    public function start(): void
    {
        $socketPath = $_SERVER[ASyncDriver::PARAM_SOCKET_PATH] ?? null;

        if (!$socketPath) {
            throw new RuntimeException('Socket path is not set.');
        }

        $timer = new Timer(
            timeoutSeconds: (int) $_SERVER[ASyncDriver::PARAM_TIMER_TIMEOUT_SECONDS],
            customStartTime: (int) $_SERVER[ASyncDriver::PARAM_TIMER_START_TIME],
        );

        $socket = $this->socketIO->createClient($socketPath);

        try {
            $response = $this->socketIO->readSocket(
                timer: $timer,
                socket: $socket
            );
        } finally {
            socket_close($socket);
        }

        $responseData = json_decode($response, true);

        $context = $this->contextTransport->unserialize($responseData['sc']);

        $this->container->set(Context::class, static fn() => $context);

        /** @var array<int> $childProcessIds */
        $childProcessIds = [];

        foreach ($responseData['pl'] as $key => $serializedCallback) {
            $childProcessIds[] = $this->forkHandler->handle(
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
                $childProcessId = $childProcessIds[$childProcessIdKey];

                $status = pcntl_waitpid($childProcessId, $status, WNOHANG | WUNTRACED);

                if ($status === $childProcessId) {
                    continue;
                }

                if ($status !== 0) {
                    throw new RuntimeException(
                        "Could not reliably manage task that uses process id [$childProcessId]"
                    );
                }

                unset($childProcessIds[$childProcessIdKey]);
            }
        }
    }
}
