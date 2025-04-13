<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use SParallel\Exceptions\ParallelTimeoutException;

interface DriverInterface
{
    /**
     * @param array<Closure> $callbacks
     *
     * @throws ParallelTimeoutException
     */
    public function wait(array $callbacks): WaitGroupInterface;
}
