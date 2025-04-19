<?php

declare(strict_types=1);

namespace SParallel\Services\Fork;

use Closure;
use RuntimeException;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\SParallelTimeoutException;
use SParallel\Objects\Context;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\ResultTransport;
use Throwable;

readonly class ForkHandler
{
    public function __construct(
        protected ResultTransport $resultTransport,
        protected SocketService $socketService,
        protected Context $context,
        protected EventsBusInterface $eventsBus,
    ) {
    }

    /**
     * Forks the current process, executes the given callback and return child process id.
     */
    public function handle(
        Timer $timer,
        string $driverName,
        string $socketPath,
        mixed $key,
        Closure $callback
    ): int {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Could not fork process.');
        }

        if ($pid !== 0) {
            return $pid;
        }

        $this->eventsBus->processCreated(pid: $pid);

        $stdout = fopen('/dev/null', 'w');

        if ($stdout === false) {
            throw new RuntimeException(
                'Could not open /dev/null for writing.'
            );
        }

        fclose(STDOUT);

        $GLOBALS['STDOUT'] = $stdout;

        $this->eventsBus->taskStarting(
            driverName: $driverName,
            context: $this->context
        );

        register_shutdown_function(
            function () use ($driverName) {
                $lastError = error_get_last();

                $this->eventsBus->taskFailed(
                    driverName: $driverName,
                    context: $this->context,
                    exception: new RuntimeException(
                        "Task was killed by the system."
                        . (is_null($lastError)
                            ? ' Unknown error.'
                            : sprintf(
                                "\nError: \"%s\" in %s:%d",
                                $lastError['message'],
                                $lastError['file'],
                                $lastError['line']
                            )
                        )
                    )
                );

                $this->eventsBus->taskFinished(
                    driverName: $driverName,
                    context: $this->context
                );
            }
        );

        try {
            $serializedResult = $this->resultTransport->serialize(
                key: $key,
                result: $callback()
            );
        } catch (Throwable $exception) {
            $this->eventsBus->taskFailed(
                driverName: $driverName,
                context: $this->context,
                exception: $exception
            );

            $serializedResult = $this->resultTransport->serialize(
                key: $key,
                exception: $exception
            );
        } finally {
            $this->eventsBus->taskFinished(
                driverName: $driverName,
                context: $this->context
            );
        }

        $socket = $this->socketService->createClient($socketPath);

        try {
            $this->socketService->writeToSocket(
                timer: $timer,
                socket: $socket,
                data: $serializedResult
            );
        } catch (SParallelTimeoutException) {
            // no action needed
        } finally {
            $this->socketService->closeSocket($socket);
        }

        posix_kill(getmypid(), SIGKILL);

        return 0;
    }
}
