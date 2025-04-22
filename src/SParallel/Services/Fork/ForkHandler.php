<?php

declare(strict_types=1);

namespace SParallel\Services\Fork;

use Closure;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\CouldNotForkProcessException;
use SParallel\Exceptions\CouldNotOpenDevNullException;
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
        mixed $taskKey,
        Closure $callback
    ): int {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new CouldNotForkProcessException($taskKey);
        }

        if ($pid !== 0) {
            return $pid;
        }

        $this->eventsBus->processCreated(pid: $pid);

        try {
            $this->onHandle(
                timer: $timer,
                driverName: $driverName,
                socketPath: $socketPath,
                key: $taskKey,
                callback: $callback
            );
        } catch (Throwable) {
            $this->eventsBus->processFinished(getmypid());

            posix_kill(getmypid(), SIGKILL);
        }

        return 0;
    }

    protected function onHandle(
        Timer $timer,
        string $driverName,
        string $socketPath,
        mixed $key,
        Closure $callback
    ): void {
        $stdout = fopen('/dev/null', 'w');

        if ($stdout === false) {
            throw new CouldNotOpenDevNullException();
        }

        fclose(STDOUT);

        $GLOBALS['STDOUT'] = $stdout;

        $this->eventsBus->taskStarting(
            driverName: $driverName,
            context: $this->context
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
    }
}
