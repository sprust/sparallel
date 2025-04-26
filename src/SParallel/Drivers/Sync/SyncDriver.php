<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Services\Canceler;
use SParallel\Services\Context;

class SyncDriver implements DriverInterface
{
    public const DRIVER_NAME = 'sync';

    public function __construct(
        protected Context $context,
        protected EventsBusInterface $eventsBus
    ) {
    }

    public function run(array &$callbacks, Canceler $canceler): WaitGroupInterface
    {
        return new SyncWaitGroup(
            callbacks: $callbacks,
            canceler: $canceler,
            context: $this->context,
            eventsBus: $this->eventsBus
        );
    }
}
