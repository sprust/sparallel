<?php

declare(strict_types=1);

namespace SParallel\Drivers\ASync;

use Closure;
use Psr\Container\ContainerInterface;
use RuntimeException;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Objects\Context;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ResultTransport;
use Throwable;

class ASyncProcess
{
    public function __construct(
        protected ContainerInterface $container,
        protected ContextTransport $contextTransport,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected SocketIO $socketIO,
        protected ?EventsBusInterface $eventsBus,
    ) {
    }

    public function start(): void
    {
        $socketPath = $_SERVER[ASyncDriver::PARAM_SOCKET_PATH] ?? null;

        if (!$socketPath) {
            throw new RuntimeException('Socket path is not set.');
        }

        $socket = $this->socketIO->createClient($socketPath);

        try {
            $response = $this->socketIO->readSocket($socket);
        } finally {
            socket_close($socket);
        }

        $responseData = json_decode($response, true);

        $context = $this->contextTransport->unserialize($responseData['sc']);

        $this->container->set(Context::class, static fn() => $context);

        /** @var array<int> $childProcessIds */
        $childProcessIds = [];

        foreach ($responseData['pl'] as $key => $serializedCallback) {
            $socketPath = $serializedCallback['sp'];
            $callback   = $this->callbackTransport->unserialize($serializedCallback['cb']);

            $childId = $this->fork(
                key: $key,
                socketPath: $socketPath,
                callback: $callback,
                context: $context
            );

            $childProcessIds[] = $childId;
        }

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

    protected function fork(mixed $key, string $socketPath, Closure $callback, Context $context): int
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Could not fork process.');
        }

        if ($pid !== 0) {
            return $pid;
        }

        $this->eventsBus?->taskStarting(
            driverName: ASyncDriver::DRIVER_NAME,
            context: $context
        );

        try {
            $serializedResult = $this->resultTransport->serialize(
                key: $key,
                result: $callback()
            );
        } catch (Throwable $exception) {
            $this->eventsBus?->taskFailed(
                driverName: ASyncDriver::DRIVER_NAME,
                context: $context,
                exception: $exception
            );

            $serializedResult = $this->resultTransport->serialize(
                key: $key,
                exception: $exception
            );
        } finally {
            $this->eventsBus?->taskFinished(
                driverName: ASyncDriver::DRIVER_NAME,
                context: $context
            );
        }

        $socket = $this->socketIO->createClient($socketPath);

        try {
            $this->socketIO->writeToSocket($socket, $serializedResult);
        } finally {
            socket_close($socket);
        }

        posix_kill(getmypid(), SIGKILL);

        return 0;
    }
}
