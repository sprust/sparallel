<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Fork;

use Closure;
use Psr\Log\LoggerInterface;
use SParallel\Contracts\TaskInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Entities\SocketServer;
use SParallel\Services\Context;

class ForkDriver implements DriverInterface
{
    public const DRIVER_NAME = 'fork';

    public function __construct(
        protected Forker $forker,
        protected ForkService $forkService,
        protected LoggerInterface $logger,
    ) {
    }

    public function init(
        Context $context,
        array $callbacks,
        int $workersLimit,
        SocketServer $socketServer
    ): void {
        //
    }

    public function create(
        Context $context,
        SocketServer $socketServer,
        int|string $key,
        Closure $callback
    ): TaskInterface {
        $forkId = $this->forker->fork(
            context: $context,
            driverName: ForkDriver::DRIVER_NAME,
            socketPath: $socketServer->path,
            taskKey: $key,
            callback: $callback
        );

        return new ForkTask(
            pid: $forkId,
            taskKey: $key,
            callback: $callback,
            forkService: $this->forkService,
            logger: $this->logger,
        );
    }

    public function break(Context $context): void
    {
        //
    }
}
