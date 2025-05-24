<?php

declare(strict_types=1);

namespace SParallel\Drivers\Server;

use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\SParallelLoggerInterface;
use SParallel\Transport\ServerTaskTransport;
use SParallel\Transport\TaskResultTransport;
use Throwable;

readonly class ServerWorker
{
    public function __construct(
        protected EventsBusInterface $eventsBus,
        protected ServerTaskTransport $serverTaskTransport,
        protected CallbackCallerInterface $callbackCaller,
        protected TaskResultTransport $taskResultTransport,
        protected SParallelLoggerInterface $logger,
    ) {
    }

    public function serve(): never
    {
        $myPid = getmypid();

        $this->logger->debug("Server worker [$myPid] started");

        while (true) {
            // TODO: stream read
            $data = fread(STDIN, 64 * 1024);

            if ($data === false) {
                usleep(100);

                continue;
            }

            $dataLen = strlen($data);

            $this->logger->debug("Server worker [$myPid] got data with length [$dataLen]");

            try {
                $task = $this->serverTaskTransport->unserialize($data);
            } catch (Throwable $exception) {
                $serializedResult = $this->taskResultTransport->serialize(
                    taskKey: uniqid('task-unserialize-error:'),
                    exception: $exception
                );

                fflush(STDOUT);
                // TODO: stream write
                fwrite(STDOUT, $serializedResult);;
                fflush(STDIN);

                continue;
            }

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

            fflush(STDOUT);
            fwrite(STDOUT, $serializedResult);
            fflush(STDIN);
        }
    }
}
