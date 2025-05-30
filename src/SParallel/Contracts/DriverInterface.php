<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use SParallel\Entities\Context;
use SParallel\Exceptions\ContextCheckerException;

interface DriverInterface
{
    /**
     * @param array<int|string, Closure> $callbacks
     *
     * @throws ContextCheckerException
     */
    public function run(Context $context, array &$callbacks, int $timeoutSeconds): WaitGroupInterface;
}
