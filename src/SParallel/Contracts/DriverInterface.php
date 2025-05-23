<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Services\Context;

interface DriverInterface
{
    /**
     * @param array<int|string, Closure> $callbacks
     *
     * @throws ContextCheckerException
     */
    public function run(Context $context, array &$callbacks, int $timeoutSeconds): WaitGroupInterface;
}
