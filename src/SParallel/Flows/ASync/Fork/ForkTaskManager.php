<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Fork;

use SParallel\Contracts\TaskInterface;
use SParallel\Contracts\TaskManagerInterface;
use SParallel\Entities\SocketServer;
use SParallel\Services\Context;

class ForkTaskManager implements TaskManagerInterface
{
    public const DRIVER_NAME = 'fork';

    public function __construct(
        protected Forker $forker,
        protected ForkService $forkService,
    ) {
    }

    public function init(
        Context $context,
        array &$callbacks,
        int $workersLimit,
        SocketServer $socketServer
    ): void {
        //
    }

    public function create(
        Context $context,
        SocketServer $socketServer,
        int|string $key,
        callable $callback
    ): TaskInterface {
        $forkId = $this->forker->fork(
            context: $context,
            driverName: ForkTaskManager::DRIVER_NAME,
            socketPath: $socketServer->path,
            taskKey: $key,
            callback: $callback
        );

        return new ForkTask(
            pid: $forkId,
            taskKey: $key,
            callback: $callback,
            forkService: $this->forkService
        );
    }
}
