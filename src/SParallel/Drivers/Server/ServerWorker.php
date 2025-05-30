<?php

declare(strict_types=1);

namespace SParallel\Drivers\Server;

use RuntimeException;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\SParallelLoggerInterface;
use SParallel\Transport\ServerTaskTransport;
use SParallel\Transport\TaskResultTransport;
use Throwable;

readonly class ServerWorker
{
    private int $lenOfHeaderLen;

    public function __construct(
        protected EventsBusInterface $eventsBus,
        protected ServerTaskTransport $serverTaskTransport,
        protected CallbackCallerInterface $callbackCaller,
        protected TaskResultTransport $taskResultTransport,
        protected SParallelLoggerInterface $logger,
    ) {
        $this->lenOfHeaderLen = 20;
    }

    public function serve(): never
    {
        $myPid = getmypid();

        $this->logger->debug("Server worker [$myPid] started");

        /** @phpstan-ignore-next-line - while.alwaysTrue */
        while (true) {
            $readPayloadLen = fread(STDIN, $this->lenOfHeaderLen);

            if ($readPayloadLen === false) {
                usleep(100);

                continue;
            }

            if (!is_numeric($readPayloadLen)) {
                $serializedResult = $this->taskResultTransport->serialize(
                    taskKey: uniqid('task-unexpected-length:'),
                    exception: new RuntimeException(
                        message: "Server worker [$myPid] got unexpected data length [$readPayloadLen]"
                    )
                );

                $this->writeResult($serializedResult);

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

            $this->logger->debug("Server worker [$myPid] got data with length [$readPayloadLen]");

            try {
                $task = $this->serverTaskTransport->unserialize($payload);
            } catch (Throwable $exception) {
                $serializedResult = $this->taskResultTransport->serialize(
                    taskKey: uniqid('task-unserialize-error:'),
                    exception: $exception
                );

                $this->writeResult($serializedResult);

                unset($serializedResult);
                
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
        }
    }

    private function writeResult(string $serializedResult): void
    {
        $lengthHeader = sprintf("%0{$this->lenOfHeaderLen}d", mb_strlen($serializedResult));

        fflush(STDOUT);
        fwrite(STDOUT, $lengthHeader . $serializedResult);
        fflush(STDIN);
    }
}
