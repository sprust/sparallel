<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use Closure;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Fork\Service\Connection;
use SParallel\Drivers\Fork\Service\Task;
use SParallel\Objects\ResultObject;
use SParallel\Transport\Serializer;
use Throwable;

class ForkDriver implements DriverInterface
{
    /**
     * @param Closure(Throwable $exception): void|null $failedTask
     */
    public function __construct(
        protected ?Closure $beforeTask = null,
        protected ?Closure $afterTask = null,
        protected ?Closure $failedTask = null,
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

            if (!is_null($this->beforeTask)) {
                call_user_func($this->beforeTask);
            }

            try {
                $socketToParent->write(
                    Serializer::serialize(
                        new ResultObject(
                            result: $callback(),
                        )
                    )
                );
            } catch (Throwable $exception) {
                if (!is_null($this->failedTask)) {
                    call_user_func($this->failedTask, $exception);
                }

                $socketToParent->write(
                    Serializer::serialize(
                        new ResultObject(
                            exception: $exception,
                        )
                    )
                );
            } finally {
                $socketToParent->close();

                if (!is_null($this->afterTask)) {
                    call_user_func($this->afterTask);
                }

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
