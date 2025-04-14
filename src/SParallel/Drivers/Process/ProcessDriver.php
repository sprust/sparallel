<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use SParallel\Contracts\WaitGroupInterface;
use SParallel\Contracts\DriverInterface;

class ProcessDriver implements DriverInterface
{
    public function wait(array $callbacks): WaitGroupInterface
    {
        return new ProcessWaitGroup(
            callbacks: $callbacks,
        );
    }
}
