<?php

declare(strict_types=1);

namespace SParallel\Tests;

use Closure;
use SParallel\Contracts\ForkStarterInterface;
use SParallel\Flows\ASync\Fork\ForkHandler;
use SParallel\Services\Context;

class TestForkStarter implements ForkStarterInterface
{
    public function __construct(
        public ForkHandler $forkHandler
    ) {
    }

    public function start(
        Context $context,
        string $driverName,
        string $socketPath,
        mixed $taskKey,
        Closure $callback
    ): void {
        $this->forkHandler->handle(
            context: $context,
            driverName: $driverName,
            socketPath: $socketPath,
            taskKey: $taskKey,
            callback: $callback
        );
    }
}
