<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use Closure;
use Generator;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Fork\Service\Connection;
use SParallel\Drivers\Fork\Service\Task;
use SParallel\Drivers\Timer;
use SParallel\Objects\Context;
use SParallel\Transport\ResultTransport;
use Throwable;

class ForkDriver implements DriverInterface
{
    public const DRIVER_NAME = 'fork';

    public function __construct(
        protected ResultTransport $resultTransport,
        protected ?Context $context = null,
        protected ?EventsBusInterface $eventsBus = null,
    ) {
    }

    public function run(array &$callbacks, Timer $timer): Generator
    {
        /** @var array<mixed, Task> $tasks */
        $tasks = [];

        $callbackKeys = array_keys($callbacks);

        foreach ($callbackKeys as $callbackKey) {
            $callback = $callbacks[$callbackKey];

            $tasks[$callbackKey] = $this->forkForTask($callbackKey, $callback);

            unset($callbacks[$callbackKey]);
        }

        while (count($tasks) > 0) {
            $timer->check();

            $keys = array_keys($tasks);

            foreach ($keys as $key) {
                $task = $tasks[$key];

                if (!$task->isFinished()) {
                    $timer->check();

                    continue;
                }

                $output = $task->output();

                unset($tasks[$key]);

                yield $this->resultTransport->unserialize($output);
            }
        }
    }

    protected function forkForTask(mixed $key, Closure $callback): Task
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
                    $this->resultTransport->serialize(
                        key: $key,
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
                    $this->resultTransport->serialize(
                        key: $key,
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
