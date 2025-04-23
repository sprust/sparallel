<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use Psr\Container\ContainerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\InvalidValueException;
use SParallel\Exceptions\SParallelTimeoutException;
use SParallel\Objects\Context;
use SParallel\Objects\ProcessChildMessage;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ProcessMessagesTransport;
use SParallel\Transport\ResultTransport;
use Throwable;

class ProcessHandler
{
    public function __construct(
        protected ContainerInterface $container,
        protected SocketService $socketService,
        protected ProcessMessagesTransport $messagesTransport,
        protected CallbackTransport $callbackTransport,
        protected ContextTransport $contextTransport,
        protected ResultTransport $resultTransport,
        protected EventsBusInterface $eventsBus,
    ) {
    }

    /**
     * @throws SParallelTimeoutException
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
     * @throws SParallelTimeoutException
     */
    protected function onHandle(): void
    {
        $taskKey        = $_SERVER[ProcessDriver::PARAM_TASK_KEY] ?? null;
        $socketPath     = $_SERVER[ProcessDriver::PARAM_SOCKET_PATH] ?? null;
        $timeoutSeconds = $_SERVER[ProcessDriver::PARAM_TIMER_TIMEOUT_SECONDS] ?? null;
        $startTime      = $_SERVER[ProcessDriver::PARAM_TIMER_START_TIME] ?? null;

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

        if (!$timeoutSeconds || !is_numeric($timeoutSeconds) || $timeoutSeconds < 1) {
            throw new InvalidValueException(
                'Timeout seconds is not set or is not numeric.'
            );
        }

        if (!$startTime || !is_numeric($startTime) || $startTime < 0) {
            throw new InvalidValueException(
                'Start time is not set or is not numeric.'
            );
        }

        $timer = new Timer(
            timeoutSeconds: (int) $_SERVER[ProcessDriver::PARAM_TIMER_TIMEOUT_SECONDS],
            customStartTime: (int) $_SERVER[ProcessDriver::PARAM_TIMER_START_TIME]
        );

        $socket = $this->socketService->createClient($socketPath);

        $this->socketService->writeToSocket(
            timer: $timer,
            socket: $socket,
            data: $this->messagesTransport->serializeChild(
                new ProcessChildMessage(
                    taskKey: $taskKey,
                    operation: 'get',
                    payload: '',
                )
            )
        );

        $response = $this->socketService->readSocket(
            timer: $timer,
            socket: $socket
        );

        $this->socketService->closeSocket($socket);

        $message = $this->messagesTransport->unserializeParent($response);

        $context = $this->contextTransport->unserialize($message->serializedContext);

        $this->container->set(Context::class, static fn() => $context);

        $driverName = ProcessDriver::DRIVER_NAME;

        $this->eventsBus->taskStarting(
            driverName: $driverName,
            context: $context
        );

        try {
            $closure = $this->callbackTransport->unserialize(
                $message->serializedCallback
            );

            $result = $closure();

            $socket = $this->socketService->createClient($socketPath);

            $this->socketService->writeToSocket(
                timer: $timer,
                socket: $socket,
                data: $this->messagesTransport->serializeChild(
                    new ProcessChildMessage(
                        taskKey: $taskKey,
                        operation: 'resp',
                        payload: $this->resultTransport->serialize(
                            key: $taskKey,
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

            $socket = $this->socketService->createClient($socketPath);

            $this->socketService->writeToSocket(
                timer: $timer,
                socket: $socket,
                data: $this->messagesTransport->serializeChild(
                    new ProcessChildMessage(
                        taskKey: $taskKey,
                        operation: 'resp',
                        payload: $this->resultTransport->serialize(
                            key: $taskKey,
                            exception: $exception
                        ),
                    )
                )
            );
        } finally {
            $this->socketService->closeSocket($socket);
        }

        $this->eventsBus->taskFinished(
            driverName: $driverName,
            context: $context
        );
    }
}
