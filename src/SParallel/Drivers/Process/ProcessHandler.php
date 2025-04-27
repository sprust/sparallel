<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\ContextResolverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\CancelerException;
use SParallel\Exceptions\InvalidValueException;
use SParallel\Objects\ProcessChildMessage;
use SParallel\Services\Canceler;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\CancelerTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ProcessMessagesTransport;
use SParallel\Transport\ResultTransport;
use Throwable;

class ProcessHandler
{
    public function __construct(
        protected ContextResolverInterface $contextSetter,
        protected SocketService $socketService,
        protected ProcessMessagesTransport $messagesTransport,
        protected CallbackTransport $callbackTransport,
        protected ContextTransport $contextTransport,
        protected CallbackCallerInterface $callbackCaller,
        protected CancelerTransport $cancelerTransport,
        protected ResultTransport $resultTransport,
        protected EventsBusInterface $eventsBus,
    ) {
    }

    /**
     * @throws CancelerException
     */
    public function handle(): void
    {
        $pid = getmypid();

        $this->eventsBus->processCreated(pid: $pid);

        try {
            $this->onHandle();
        } finally {
            $this->eventsBus->processFinished(pid: $pid);
        }
    }

    /**
     * @throws CancelerException
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

        $socketClient = $this->socketService->createClient($socketPath);

        $initCanceler = (new Canceler())->add(new Timer(timeoutSeconds: 2));

        $this->socketService->writeToSocket(
            canceler: $initCanceler,
            socket: $socketClient->socket,
            data: $this->messagesTransport->serializeChild(
                new ProcessChildMessage(
                    taskKey: $taskKey,
                    operation: 'get',
                    payload: '',
                )
            )
        );

        $response = $this->socketService->readSocket(
            canceler: $initCanceler,
            socket: $socketClient->socket
        );

        unset($socketClient);

        $message = $this->messagesTransport->unserializeParent($response);

        $context = $this->contextTransport->unserialize($message->serializedContext);

        $this->contextSetter->set($context);

        $canceler = $this->cancelerTransport->unserialize($message->serializedCanceler);

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
                canceler: $canceler
            );

            $socketClient = $this->socketService->createClient($socketPath);

            $this->socketService->writeToSocket(
                canceler: $canceler,
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
                canceler: $canceler,
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
