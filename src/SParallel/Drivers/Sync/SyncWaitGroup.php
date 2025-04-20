<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use Generator;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Timer;
use SParallel\Objects\Context;
use SParallel\Objects\ResultObject;
use Throwable;

class SyncWaitGroup implements WaitGroupInterface
{
    /**
     * @param array<mixed, callable> $callbacks
     */
    public function __construct(
        protected array &$callbacks,
        protected Timer $timer,
        protected Context $context,
        protected EventsBusInterface $eventsBus
    ) {
    }

    public function get(): Generator
    {
        $callbackKeys = array_keys($this->callbacks);

        foreach ($callbackKeys as $callbackKey) {
            $this->timer->check();

            $callback = $this->callbacks[$callbackKey];

            $this->eventsBus->taskStarting(
                driverName: SyncDriver::DRIVER_NAME,
                context: $this->context
            );

            try {
                $result = new ResultObject(
                    key: $callbackKey,
                    result: $callback()
                );
            } catch (Throwable $exception) {
                $this->eventsBus->taskFailed(
                    driverName: SyncDriver::DRIVER_NAME,
                    context: $this->context,
                    exception: $exception
                );

                $result = new ResultObject(
                    key: $callbackKey,
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
        $keys = array_keys($this->callbacks);

        foreach ($keys as $key) {
            unset($this->callbacks[$key]);
        }
    }
}
