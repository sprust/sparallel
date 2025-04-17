<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use Closure;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Fork\Service\Connection;
use SParallel\Drivers\Fork\Service\Task;
use SParallel\Objects\Context;
use SParallel\Transport\ResultTransport;
use Throwable;

class ForkDriver implements DriverInterface
{
    public const DRIVER_NAME = 'fork';

    public function __construct(
        protected ResultTransport $taskResultTransport,
        protected ?Context $context = null,
        protected ?EventsBusInterface $eventsBus = null,
    ) {
    }

    public function wait(array $callbacks): WaitGroupInterface
    {
        return new ForkWaitGroup(
            taskResultTransport: $this->taskResultTransport,
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

            $this->eventsBus?->taskStarting(
                driverName: static::DRIVER_NAME,
                context: $this->context
            );

            try {
                $socketToParent->write(
                    $this->taskResultTransport->serialize(
                        result: $callback(),
                    )
                );
            } catch (Throwable $exception) {
                $this->eventsBus?->taskFailed(
                    driverName: static::DRIVER_NAME,
                    context: $this->context,
                    exception: $exception
                );

                $socketToParent->write(
                    $this->taskResultTransport->serialize(
                        exception: $exception,
                    )
                );
            } finally {
                $socketToParent->close();

                $this->eventsBus?->taskFinished(
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
