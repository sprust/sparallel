<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Fork;

use Closure;
use Psr\Log\LoggerInterface;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Enum\MessageOperationTypeEnum;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Objects\Message;
use SParallel\Services\Context;
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
        protected LoggerInterface $logger,
    ) {
    }

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

        try {
            $this->onHandle(
                context: $context,
                myPid: $myPid,
                driverName: $driverName,
                socketPath: $socketPath,
                taskKey: $taskKey,
                callback: $callback
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
        } finally {
            $this->logger->debug(
                sprintf(
                    "fork finished [fPid: %d, tKey: %s]",
                    $myPid,
                    $taskKey
                )
            );

            $this->eventsBus->processFinished(pid: $myPid);
        }

        posix_kill($myPid, SIGTERM);
    }

    /**
     * @throws ContextCheckerException
     */
    protected function onHandle(
        Context $context,
        int $myPid,
        string $driverName,
        string $socketPath,
        int|string $taskKey,
        Closure $callback
    ): void {
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

            throw $exception;
        } finally {
            unset($client);
        }
    }
}
