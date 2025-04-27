<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\ContextResolverInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Services\Canceler;

class SyncDriver implements DriverInterface
{
    public const DRIVER_NAME = 'sync';

    public function __construct(
        protected ContextResolverInterface $contextResolver,
        protected EventsBusInterface $eventsBus,
        protected CallbackCallerInterface $callbackCaller,
    ) {
    }

    public function run(array &$callbacks, Canceler $canceler): WaitGroupInterface
    {
        return new SyncWaitGroup(
            callbacks: $callbacks,
            canceler: $canceler,
            contextResolver: $this->contextResolver,
            eventsBus: $this->eventsBus,
            callbackCaller: $this->callbackCaller
        );
    }
}
