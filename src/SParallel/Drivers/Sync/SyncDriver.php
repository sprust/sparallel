<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Timer;
use SParallel\Objects\Context;

class SyncDriver implements DriverInterface
{
    public const DRIVER_NAME = 'sync';

    public function __construct(
        protected Context $context,
        protected EventsBusInterface $eventsBus
    ) {
    }

    public function run(array &$callbacks, Timer $timer): WaitGroupInterface
    {
        return new SyncWaitGroup(
            callbacks: $callbacks,
            timer: $timer,
            context: $this->context,
            eventsBus: $this->eventsBus
        );
    }
}
