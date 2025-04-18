<?php

declare(strict_types=1);

namespace SParallel\Drivers\ASync;

use Closure;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Socket;
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
        protected SocketIO $socketIO
    ) {
    }

    public function start(): void
    {
        $socketPath = $_SERVER[ASyncDriver::PARAM_SOCKET_PATH] ?? null;

        if (!$socketPath) {
            throw new RuntimeException('Socket path is not set.');
        }

        $socket = $this->createClientSocket($socketPath);

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
                callback: $callback
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

    private function fork(mixed $key, string $socketPath, Closure $callback): int
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Could not fork process.');
        }

        if ($pid !== 0) {
            return $pid;
        }

        try {
            $serializedResult = $this->resultTransport->serialize(
                key: $key,
                result: $callback()
            );
        } catch (Throwable $exception) {
            $serializedResult = $this->resultTransport->serialize(
                key: $key,
                exception: $exception
            );
        }

        $socket = $this->createClientSocket($socketPath);

        try {
            $this->socketIO->writeToSocket($socket, $serializedResult);
        } finally {
            socket_close($socket);
        }

        posix_kill(getmypid(), SIGKILL);

        return 0;
    }

    private function createClientSocket(string $socketPath): Socket
    {
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

        if (!socket_connect($socket, $socketPath)) {
            throw new RuntimeException(
                'Could not connect to socket: ' . socket_strerror(socket_last_error($socket))
            );
        }

        return $socket;
    }
}
