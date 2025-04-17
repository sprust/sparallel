<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use SParallel\Exceptions\SParallelTimeoutException;

interface DriverInterface
{
    /**
     * @param array<mixed, Closure> $callbacks
     *
     * @throws SParallelTimeoutException
     */
    public function wait(array $callbacks): WaitGroupInterface;
}
