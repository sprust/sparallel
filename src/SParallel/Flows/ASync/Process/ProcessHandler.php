<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Process;

use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Entities\Timer;
use SParallel\Enum\MessageOperationTypeEnum;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\InvalidValueException;
use SParallel\Objects\Message;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\MessageTransport;
use SParallel\Transport\ResultTransport;
use Throwable;

class ProcessHandler
{
    public function __construct(
        protected SocketService $socketService,
        protected MessageTransport $messageTransport,
        protected CallbackTransport $callbackTransport,
        protected ContextTransport $contextTransport,
        protected CallbackCallerInterface $callbackCaller,
        protected ResultTransport $resultTransport,
        protected EventsBusInterface $eventsBus,
        protected ProcessService $processService,
    ) {
    }

    /**
     * @throws ContextCheckerException
     */
    public function handle(): void
    {
        $myPid = getmypid();

        $this->eventsBus->processCreated(pid: $myPid);

        $exitHandler = function (string $method) use ($myPid) {
            $this->eventsBus->processFinished(pid: $myPid);

            posix_kill($myPid, SIGKILL);
        };

        $this->processService->registerShutdownFunction($exitHandler);
        $this->processService->registerExitSignals($exitHandler);

        $this->onHandle();
    }

    /**
     * @throws ContextCheckerException
     */
    protected function onHandle(): void
    {
        $taskKey    = $_SERVER[ProcessDriver::PARAM_TASK_KEY] ?? null;
        $socketPath = $_SERVER[ProcessDriver::PARAM_SOCKET_PATH] ?? null;

        if (is_null($taskKey)) {
            throw new InvalidValueException(
                'Task key is not set.'
            );
        }

        if (!$socketPath || !is_string($socketPath)) {
            throw new InvalidValueException(
                'Socket path is not set or is not a string.'
            );
        }

        $taskKey = unserialize($taskKey);

        $socketClient = $this->socketService->createClient($socketPath);

        $initContext = new Context();

        $this->socketService->writeToSocket(
            context: $initContext->setChecker(new Timer(timeoutSeconds: 2)),
            socket: $socketClient->socket,
            data: $this->messageTransport->serialize(
                new Message(
                    operation: MessageOperationTypeEnum::GetJob,
                    taskKey: $taskKey,
                )
            )
        );

        $initContext->clear();

        $response = $this->socketService->readSocket(
            context: $initContext->setChecker(new Timer(timeoutSeconds: 2)),
            socket: $socketClient->socket
        );

        unset($socketClient);

        $message = $this->messageTransport->unserialize($response);

        $context = $this->contextTransport->unserialize($message->serializedContext);

        $driverName = ProcessDriver::DRIVER_NAME;

        $this->eventsBus->taskStarting(
            driverName: $driverName,
            context: $context
        );

        try {
            $callback = $this->callbackTransport->unserialize(
                $message->payload
            );

            $result = $this->callbackCaller->call(
                callback: $callback,
                context: $context
            );

            $socketClient = $this->socketService->createClient($socketPath);

            $this->socketService->writeToSocket(
                context: $context,
                socket: $socketClient->socket,
                data: $this->messageTransport->serialize(
                    new Message(
                        operation: MessageOperationTypeEnum::Response,
                        taskKey: $taskKey,
                        payload: $this->resultTransport->serialize(
                            taskKey: $taskKey,
                            result: $result
                        ),
                    )
                )
            );
        } catch (Throwable $exception) {
            $this->eventsBus->taskFailed(
                driverName: $driverName,
                context: $context,
                exception: $exception
            );

            $socketClient = $this->socketService->createClient($socketPath);

            $this->socketService->writeToSocket(
                context: $context,
                socket: $socketClient->socket,
                data: $this->messageTransport->serialize(
                    new Message(
                        operation: MessageOperationTypeEnum::Response,
                        taskKey: $taskKey,
                        payload: $this->resultTransport->serialize(
                            taskKey: $taskKey,
                            exception: $exception
                        ),
                    )
                )
            );
        } finally {
            $this->eventsBus->taskFinished(
                driverName: $driverName,
                context: $context
            );
        }

        unset($socketClient);
    }
}
