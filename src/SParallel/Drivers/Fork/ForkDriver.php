<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use Closure;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Fork\Service\Connection;
use SParallel\Drivers\Fork\Service\Task;
use Throwable;

class ForkDriver implements DriverInterface
{
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

            try {
                $socketToParent->write(
                    json_encode([
                        'success' => true,
                        'data'    => \Opis\Closure\serialize($callback()),
                    ])
                );
            } catch (Throwable $exception) {
                $socketToParent->write(
                    json_encode([
                        'success' => false,
                        'data'    => \Opis\Closure\serialize($exception->getMessage()),
                    ])
                );
            } finally {
                $socketToParent->close();

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
