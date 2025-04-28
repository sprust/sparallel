<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use SParallel\Exceptions\CancelerException;
use SParallel\Services\Canceler;

interface DriverInterface
{
    /**
     * @param array<mixed, Closure> $callbacks
     *
     * @throws CancelerException
     */
    public function run(array &$callbacks, Canceler $canceler, int $workersLimit): WaitGroupInterface;
}
