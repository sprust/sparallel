<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\InvalidValueException;
use SParallel\Objects\ProcessChildMessage;
use SParallel\Services\Context;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ProcessMessagesTransport;
use SParallel\Transport\ResultTransport;
use Throwable;

class ProcessHandler
{
    public function __construct(
        protected SocketService $socketService,
        protected ProcessMessagesTransport $messagesTransport,
        protected CallbackTransport $callbackTransport,
        protected ContextTransport $contextTransport,
        protected CallbackCallerInterface $callbackCaller,
        protected ResultTransport $resultTransport,
        protected EventsBusInterface $eventsBus,
    ) {
    }

    /**
     * @throws ContextCheckerException
     */
    public function handle(): void
    {
        $pid = getmypid();

        try {
            $this->onHandle();
        } finally {
            $this->eventsBus->processFinished(pid: $pid);
        }
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
            context: $initContext->addChecker(new Timer(timeoutSeconds: 2)),
            socket: $socketClient->socket,
            data: $this->messagesTransport->serializeChild(
                new ProcessChildMessage(
                    taskKey: $taskKey,
                    operation: 'get',
                    payload: '',
                )
            )
        );

        $initContext->clear();

        $response = $this->socketService->readSocket(
            context: $initContext->addChecker(new Timer(timeoutSeconds: 2)),
            socket: $socketClient->socket
        );

        unset($socketClient);

        $message = $this->messagesTransport->unserializeParent($response);

        $context = $this->contextTransport->unserialize($message->serializedContext);

        $driverName = ProcessDriver::DRIVER_NAME;

        $this->eventsBus->taskStarting(
            driverName: $driverName,
            context: $context
        );

        try {
            $callback = $this->callbackTransport->unserialize(
                $message->serializedCallback
            );

            $result = $this->callbackCaller->call(
                callback: $callback,
                context: $context
            );

            $socketClient = $this->socketService->createClient($socketPath);

            $this->socketService->writeToSocket(
                context: $context,
                socket: $socketClient->socket,
                data: $this->messagesTransport->serializeChild(
                    new ProcessChildMessage(
                        taskKey: $taskKey,
                        operation: 'resp',
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
                data: $this->messagesTransport->serializeChild(
                    new ProcessChildMessage(
                        taskKey: $taskKey,
                        operation: 'resp',
                        payload: $this->resultTransport->serialize(
                            taskKey: $taskKey,
                            exception: $exception
                        ),
                    )
                )
            );
        }

        unset($socketClient);

        $this->eventsBus->taskFinished(
            driverName: $driverName,
            context: $context
        );
    }
}
