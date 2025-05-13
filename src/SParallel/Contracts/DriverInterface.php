<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use SParallel\Entities\SocketServer;
use SParallel\Services\Context;

interface DriverInterface
{
    /**
     * @param array<int|string, Closure> $callbacks
     */
    public function init(
        Context $context,
        array $callbacks,
        int $workersLimit,
        SocketServer $socketServer
    ): void;

    public function create(
        Context $context,
        SocketServer $socketServer,
        int|string $key,
        Closure $callback
    ): TaskInterface;

    public function break(Context $context): void;
}
