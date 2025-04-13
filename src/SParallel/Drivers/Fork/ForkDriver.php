<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\WaitGroupInterface;

class ForkDriver implements DriverInterface
{
    public function wait(array $callbacks): WaitGroupInterface
    {
        // TODO: Implement run() method.
    }
}
