<?php

declare(strict_types=1);

namespace SParallel\Services\Fork;

use Closure;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\CouldNotForkProcessException;
use SParallel\Services\Context;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\ResultTransport;
use Throwable;

readonly class ForkHandler
{
    public function __construct(
        protected ResultTransport $resultTransport,
        protected SocketService $socketService,
        protected CallbackCallerInterface $callbackCaller,
        protected EventsBusInterface $eventsBus,
    ) {
    }

    /**
     * Forks the current process, executes the given callback and return child process id.
     */
    public function handle(
        Context $context,
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

        $myPid = getmypid();

        $this->eventsBus->processCreated(pid: $myPid);

        try {
            $this->onHandle(
                context: $context,
                driverName: $driverName,
                socketPath: $socketPath,
                taskKey: $taskKey,
                callback: $callback
            );
        } finally {
            $this->eventsBus->processFinished($myPid);

            posix_kill($myPid, SIGKILL);
        }

        return 0;
    }

    protected function onHandle(
        Context $context,
        string $driverName,
        string $socketPath,
        mixed $taskKey,
        Closure $callback
    ): void {
        // TODO: crushing sometimes
        //$stdout = fopen('/dev/null', 'w');
        //
        //if ($stdout === false) {
        //    throw new CouldNotOpenDevNullException();
        //}
        //
        //fclose(STDOUT);
        //
        //$GLOBALS['STDOUT'] = $stdout;

        $this->eventsBus->taskStarting(
            driverName: $driverName,
            context: $context
        );

        try {
            $serializedResult = $this->resultTransport->serialize(
                taskKey: $taskKey,
                result: $this->callbackCaller->call(
                    callback: $callback,
                    context: $context
                )
            );
        } catch (Throwable $exception) {
            $this->eventsBus->taskFailed(
                driverName: $driverName,
                context: $context,
                exception: $exception
            );

            $serializedResult = $this->resultTransport->serialize(
                taskKey: $taskKey,
                exception: $exception
            );
        } finally {
            $this->eventsBus->taskFinished(
                driverName: $driverName,
                context: $context
            );
        }

        $socketClient = $this->socketService->createClient($socketPath);

        try {
            $this->socketService->writeToSocket(
                context: $context,
                socket: $socketClient->socket,
                data: $serializedResult
            );
        } catch (ContextCheckerException) {
            // no action needed
        }
    }
}
