<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Fork;

use Closure;
use Psr\Log\LoggerInterface;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Enum\MessageOperationTypeEnum;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\CouldNotOpenDevNullException;
use SParallel\Objects\Message;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\MessageTransport;
use SParallel\Transport\ResultTransport;
use Throwable;

readonly class ForkHandler
{
    public function __construct(
        protected ResultTransport $resultTransport,
        protected SocketService $socketService,
        protected CallbackCallerInterface $callbackCaller,
        protected EventsBusInterface $eventsBus,
        protected MessageTransport $messageTransport,
        protected ProcessService $processService,
        protected LoggerInterface $logger,
    ) {
    }

    /**
     * Forks the current process, executes the given callback and return child process id.
     */
    public function handle(
        Context $context,
        string $driverName,
        string $socketPath,
        int|string $taskKey,
        Closure $callback
    ): void {
        $myPid = getmypid();

        $this->logger->debug(
            sprintf(
                "fork started [fPid: %d, tKey: %s]",
                $myPid,
                $taskKey
            )
        );

        $this->eventsBus->processCreated(pid: $myPid);

        // TODO: it doesnt work at hybrid usage with 'Allowed memory size' error
        $exitHandler = function (string $method) use ($myPid) {
            $this->eventsBus->processFinished(pid: $myPid);

            if ($lastError = error_get_last()) {
                $this->logger->error(
                    sprintf(
                        "fork got error in closing handler [fPid: %d]: %s\n%s:%s",
                        $myPid,
                        $lastError['message'],
                        $lastError['file'],
                        $lastError['line'],
                    )
                );
            }

            $this->logger->debug(
                sprintf(
                    "fork closing by handler [$method] [fPid: %s]",
                    $myPid
                )
            );

            posix_kill($myPid, SIGKILL);
        };

        $this->processService->registerShutdownFunction($exitHandler);
        $this->processService->registerExitSignals($exitHandler);

        try {
            // TODO: WARNING: crushing sometimes
            $stdout = fopen('/dev/null', 'w');

            if ($stdout === false) {
                throw new CouldNotOpenDevNullException();
            }

            fclose(STDOUT);

            $GLOBALS['STDOUT'] = $stdout;

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

                $this->logger->debug(
                    sprintf(
                        "fork called task [fPid: %d, tKey: %s]",
                        $myPid,
                        $taskKey,
                    )
                );
            } catch (Throwable $exception) {
                $this->logger->error(
                    sprintf(
                        "fork got error at handling [fPid: %d, tKey: %s]: %s\n%s",
                        $myPid,
                        $taskKey,
                        $exception->getMessage(),
                        $exception
                    )
                );

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

            $client = $this->socketService->createClient($socketPath);

            try {
                $this->socketService->writeToSocket(
                    context: $context,
                    socket: $client->socket,
                    data: $this->messageTransport->serialize(
                        new Message(
                            operation: MessageOperationTypeEnum::Response,
                            taskKey: $taskKey,
                            payload: $serializedResult,
                        )
                    )
                );

                $this->logger->debug(
                    sprintf(
                        "fork answered to flow [fPid: %d, tKey: %s]",
                        $myPid,
                        $taskKey
                    )
                );
            } catch (ContextCheckerException $exception) {
                $this->logger->error(
                    sprintf(
                        "fork got error at answering to flow [fPid: %d, tKey: %s]: %s\n%s",
                        $myPid,
                        $taskKey,
                        $exception->getMessage(),
                        $exception
                    )
                );
            }

            unset($client);
        } finally {
            posix_kill($myPid, SIGTERM);
        }
    }
}
