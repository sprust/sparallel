<?php

declare(strict_types=1);

namespace SParallel\Flows\Sync;

use Closure;
use Generator;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\FlowInterface;
use SParallel\Contracts\TaskManagerInterface;
use SParallel\Entities\SocketServer;
use SParallel\Objects\TaskResult;
use SParallel\Services\Context;
use Throwable;

class SyncFlow implements FlowInterface
{
    public const DRIVER_NAME = 'sync';

    protected array $callbacks;
    protected Context $context;

    public function __construct(
        protected EventsBusInterface $eventsBus,
        protected CallbackCallerInterface $callbackCaller
    ) {
    }

    /**
     * @param array<mixed, Closure> $callbacks
     */
    public function start(
        Context $context,
        TaskManagerInterface $taskManager,
        array &$callbacks,
        int $workersLimit,
        SocketServer $socketServer
    ): static {
        $this->callbacks = $callbacks;
        $this->context   = $context;

        return $this;
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
                        callback: $callback,
                        context: $this->context
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

    public function break(): void
    {
        $taskKeys = array_keys($this->callbacks);

        foreach ($taskKeys as $taskKey) {
            unset($this->callbacks[$taskKey]);
        }
    }
}
