<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use SParallel\Entities\SocketServer;
use SParallel\Services\Context;

interface TaskManagerInterface
{
    /**
     * @param array<int|string> $callbacks
     */
    public function init(
        Context $context,
        array &$callbacks,
        int $workersLimit,
        SocketServer $socketServer
    ): void;

    public function create(
        Context $context,
        SocketServer $socketServer,
        int|string $key,
        callable $callback
    ): TaskInterface;
}
