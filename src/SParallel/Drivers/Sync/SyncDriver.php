<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Objects\Context;

class SyncDriver implements DriverInterface
{
    public const DRIVER_NAME = 'sync';

    public function __construct(
        protected ?Context $context = null,
        protected ?EventsBusInterface $eventsBus = null
    ) {
    }

    public function wait(array $callbacks): WaitGroupInterface
    {
        return new SyncWaitGroup(
            callbacks: $callbacks,
            context: $this->context,
            eventsBus: $this->eventsBus
        );
    }
}
