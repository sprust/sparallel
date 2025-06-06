<?php

declare(strict_types=1);

namespace SParallel\Drivers\Server;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\ContainerFactoryInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Transport\ServerTaskTransport;
use SParallel\Transport\TaskResultTransport;
use Throwable;

class ServerWorker
{
    private readonly int $lenOfHeaderLen;

    private ?ContainerInterface $container;
    private ?TaskResultTransport $taskResultTransport;
    private ?EventsBusInterface $eventsBus;
    private ?ServerTaskTransport $serverTaskTransport;
    private ?CallbackCallerInterface $callbackCaller;

    public function __construct()
    {
        $this->lenOfHeaderLen = 20;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function serve(ContainerFactoryInterface $containerFactory): never
    {
        $myPid = getmypid();

        /** @phpstan-ignore-next-line - while.alwaysTrue */
        while (true) {
            $readPayloadLen = fread(STDIN, $this->lenOfHeaderLen);

            if ($readPayloadLen === false) {
                usleep(100);

                continue;
            }

            $this->onStartingTask($containerFactory);

            if (!is_numeric($readPayloadLen)) {
                $serializedResult = $this->taskResultTransport->serialize(
                    taskKey: uniqid('task-unexpected-length:'),
                    exception: new RuntimeException(
                        message: "Server worker [$myPid] got unexpected data length [$readPayloadLen]"
                    )
                );

                $this->writeResult($serializedResult);

                unset($serializedResult);
                $this->onFinishedTask();

                continue;
            }

            $payloadLen = (int) $readPayloadLen;

            $payload = '';

            while (strlen($payload) < $payloadLen) {
                $data = fread(STDIN, $payloadLen - strlen($payload));

                if ($data === false) {
                    usleep(100);

                    continue;
                }

                $payload .= $data;

                $payloadLen = strlen($payload);
            }

            try {
                $task = $this->serverTaskTransport->unserialize($payload);
            } catch (Throwable $exception) {
                $serializedResult = $this->taskResultTransport->serialize(
                    taskKey: uniqid('task-unserialize-error:'),
                    exception: $exception
                );

                $this->writeResult($serializedResult);

                unset($serializedResult);
                $this->onFinishedTask();

                continue;
            }

            unset($payload);

            $this->eventsBus->taskStarting(
                driverName: ServerDriver::DRIVER_NAME,
                context: $task->context
            );

            try {
                $serializedResult = $this->taskResultTransport->serialize(
                    taskKey: $task->key,
                    result: $this->callbackCaller->call(
                        $task->context,
                        $task->callback
                    )
                );
            } catch (Throwable $exception) {
                $this->eventsBus->taskFailed(
                    driverName: ServerDriver::DRIVER_NAME,
                    context: $task->context,
                    exception: $exception
                );

                $serializedResult = $this->taskResultTransport->serialize(
                    taskKey: $task->key,
                    exception: $exception
                );
            } finally {
                $this->eventsBus->taskFinished(
                    driverName: ServerDriver::DRIVER_NAME,
                    context: $task->context
                );
            }

            $this->writeResult($serializedResult);

            unset($serializedResult);
            $this->onFinishedTask();
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function onStartingTask(ContainerFactoryInterface $containerFactory): void
    {
        $this->container = $containerFactory->create();

        $this->taskResultTransport = $this->container->get(TaskResultTransport::class);
        $this->eventsBus           = $this->container->get(EventsBusInterface::class);
        $this->serverTaskTransport = $this->container->get(ServerTaskTransport::class);
        $this->callbackCaller      = $this->container->get(CallbackCallerInterface::class);
    }

    private function onFinishedTask(): void
    {
        $this->taskResultTransport = null;
        $this->eventsBus           = null;
        $this->serverTaskTransport = null;
        $this->callbackCaller      = null;

        $this->container = null;
    }

    private function writeResult(string $serializedResult): void
    {
        $lengthHeader = sprintf("%0{$this->lenOfHeaderLen}d", mb_strlen($serializedResult));

        fflush(STDOUT);
        fwrite(STDOUT, $lengthHeader . $serializedResult);
        fflush(STDIN);
    }
}
