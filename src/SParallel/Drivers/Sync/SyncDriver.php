<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Services\Context;

class SyncDriver implements DriverInterface
{
    public const DRIVER_NAME = 'sync';

    public function __construct(
        protected EventsBusInterface $eventsBus,
        protected CallbackCallerInterface $callbackCaller,
    ) {
    }

    public function run(array &$callbacks, Context $context, int $workersLimit): WaitGroupInterface
    {
        return new SyncWaitGroup(
            callbacks: $callbacks,
            context: $context,
            eventsBus: $this->eventsBus,
            callbackCaller: $this->callbackCaller
        );
    }
}
