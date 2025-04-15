<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use Closure;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\TaskEventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Fork\Service\Connection;
use SParallel\Drivers\Fork\Service\Task;
use SParallel\Objects\Context;
use SParallel\Objects\ResultObject;
use SParallel\Transport\Serializer;
use Throwable;

class ForkDriver implements DriverInterface
{
    public const DRIVER_NAME = 'fork';

    public function __construct(
        protected ?Context $context = null,
        protected ?TaskEventsBusInterface $taskEventsBus = null
    ) {
    }

    public function wait(array $callbacks): WaitGroupInterface
    {
        return new ForkWaitGroup(
            tasks: array_map(
                fn(Closure $callback) => $this->forkForTask($callback),
                $callbacks
            ),
        );
    }

    protected function forkForTask(Closure $callback): Task
    {
        [$socketToParent, $socketToChild] = Connection::createPair();

        $pid = pcntl_fork();

        if ($pid === 0) {
            $socketToChild->close();

            $this->taskEventsBus?->starting(
                driverName: static::DRIVER_NAME,
                context: $this->context
            );

            try {
                $socketToParent->write(
                    Serializer::serialize(
                        new ResultObject(
                            result: $callback(),
                        )
                    )
                );
            } catch (Throwable $exception) {
                $this->taskEventsBus?->failed(
                    driverName: static::DRIVER_NAME,
                    context: $this->context,
                    exception: $exception
                );

                $socketToParent->write(
                    Serializer::serialize(
                        new ResultObject(
                            exception: $exception,
                        )
                    )
                );
            } finally {
                $socketToParent->close();

                $this->taskEventsBus?->finished(
                    driverName: static::DRIVER_NAME,
                    context: $this->context
                );

                posix_kill(getmypid(), SIGKILL);
            }
        }

        $socketToParent->close();

        return new Task(
            pid: $pid,
            connection: $socketToChild
        );
    }
}
