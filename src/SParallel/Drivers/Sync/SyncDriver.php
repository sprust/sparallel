<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\WaitGroupInterface;

class SyncDriver implements DriverInterface
{
    public function wait(array $callbacks): WaitGroupInterface
    {
        return new SyncWaitGroup(
            callbacks: $callbacks
        );
    }
}
