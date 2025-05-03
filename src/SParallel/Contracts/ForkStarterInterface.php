<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use SParallel\Services\Context;

interface ForkStarterInterface
{
    public function start(
        Context $context,
        string $driverName,
        string $socketPath,
        mixed $taskKey,
        Closure $callback
    ): void;
}
