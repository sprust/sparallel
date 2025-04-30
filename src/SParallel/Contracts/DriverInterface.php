<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Services\Context;

interface DriverInterface
{
    /**
     * @param array<mixed, Closure> $callbacks
     *
     * @throws ContextCheckerException
     */
    public function run(array &$callbacks, Context $context, int $workersLimit): WaitGroupInterface;
}
