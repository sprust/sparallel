<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use Closure;
use Generator;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Flows\Sync\SyncFlow;
use SParallel\Objects\TaskResult;
use SParallel\Services\Context;
use Throwable;

class SyncWaitGroup implements WaitGroupInterface
{
    /**
     * @param array<int|string, Closure> $callbacks
     */
    public function __construct(
        private readonly Context $context,
        private array &$callbacks,
        private readonly EventsBusInterface $eventsBus,
        private readonly CallbackCallerInterface $callbackCaller,
    ) {
    }

    public function get(): Generator
    {
        $callbackKeys = array_keys($this->callbacks);

        foreach ($callbackKeys as $callbackKey) {
            $this->context->check();

            $callback = $this->callbacks[$callbackKey];

            $this->eventsBus->taskStarting(
                driverName: SyncFlow::DRIVER_NAME,
                context: $this->context
            );

            try {
                $result = new TaskResult(
                    taskKey: $callbackKey,
                    result: $this->callbackCaller->call(
                        context: $this->context,
                        callback: $callback
                    )
                );
            } catch (Throwable $exception) {
                $this->eventsBus->taskFailed(
                    driverName: SyncFlow::DRIVER_NAME,
                    context: $this->context,
                    exception: $exception
                );

                $result = new TaskResult(
                    taskKey: $callbackKey,
                    exception: $exception
                );
            } finally {
                $this->eventsBus->taskFinished(
                    driverName: SyncFlow::DRIVER_NAME,
                    context: $this->context
                );
            }

            unset($this->callbacks[$callbackKey]);

            yield $result;
        }
    }

    public function cancel(): void
    {
        // no action
    }
}
