<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Services\Context;

readonly class SyncDriver implements DriverInterface
{
    public function __construct(
        private EventsBusInterface $eventsBus,
        private CallbackCallerInterface $callbackCaller,
    ) {
    }

    public function run(Context $context, array &$callbacks, int $timeoutSeconds): WaitGroupInterface
    {
        return new SyncWaitGroup(
            context: $context,
            callbacks: $callbacks,
            eventsBus: $this->eventsBus,
            callbackCaller: $this->callbackCaller
        );
    }
}
