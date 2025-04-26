<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use Closure;
use Generator;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Objects\TaskResult;
use SParallel\Services\Canceler;
use SParallel\Services\Context;
use Throwable;

class SyncWaitGroup implements WaitGroupInterface
{
    /**
     * @param array<mixed, Closure> $callbacks
     */
    public function __construct(
        protected array &$callbacks,
        protected Canceler $canceler,
        protected Context $context,
        protected EventsBusInterface $eventsBus,
        protected CallbackCallerInterface $callbackCaller
    ) {
    }

    public function get(): Generator
    {
        $callbackKeys = array_keys($this->callbacks);

        foreach ($callbackKeys as $callbackKey) {
            $this->canceler->check();

            $callback = $this->callbacks[$callbackKey];

            $this->eventsBus->taskStarting(
                driverName: SyncDriver::DRIVER_NAME,
                context: $this->context
            );

            try {
                $result = new TaskResult(
                    taskKey: $callbackKey,
                    result: $this->callbackCaller->call(
                        callback: $callback,
                        canceler: $this->canceler
                    )
                );
            } catch (Throwable $exception) {
                $this->eventsBus->taskFailed(
                    driverName: SyncDriver::DRIVER_NAME,
                    context: $this->context,
                    exception: $exception
                );

                $result = new TaskResult(
                    taskKey: $callbackKey,
                    exception: $exception
                );
            } finally {
                $this->eventsBus->taskFinished(
                    driverName: SyncDriver::DRIVER_NAME,
                    context: $this->context
                );
            }

            unset($this->callbacks[$callbackKey]);

            yield $result;
        }
    }

    public function break(): void
    {
        $taskKeys = array_keys($this->callbacks);

        foreach ($taskKeys as $taskKey) {
            unset($this->callbacks[$taskKey]);
        }
    }
}
