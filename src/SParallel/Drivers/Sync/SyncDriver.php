<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use Generator;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Timer;
use SParallel\Objects\Context;
use SParallel\Objects\ResultObject;
use Throwable;

class SyncDriver implements DriverInterface
{
    public const DRIVER_NAME = 'sync';

    public function __construct(
        protected Context $context,
        protected EventsBusInterface $eventsBus
    ) {
    }

    public function run(array &$callbacks, Timer $timer): Generator
    {
        $callbackKeys = array_keys($callbacks);

        foreach ($callbackKeys as $callbackKey) {
            $timer->check();

            $callback = $callbacks[$callbackKey];

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

            unset($callbacks[$callbackKey]);

            yield $result;
        }
    }
}
