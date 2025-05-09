<?php

declare(strict_types=1);

namespace SParallel\Flows;

use Closure;
use SParallel\Contracts\FlowInterface;
use SParallel\Contracts\TaskManagerFactoryInterface;
use SParallel\Contracts\TaskManagerInterface;
use SParallel\Entities\SocketServer;
use SParallel\Services\Context;
use SParallel\Services\Socket\SocketService;

readonly class FlowFactory
{
    public function __construct(
        protected SocketService $socketService,
        protected TaskManagerFactoryInterface $taskManagerFactory,
        protected FlowInterface $flow,
    ) {
    }

    /**
     * @param array<int|string, Closure> $callbacks
     */
    public function create(
        array &$callbacks,
        Context $context,
        int $workersLimit,
        ?TaskManagerInterface $taskManager = null,
        ?SocketServer $socketServer = null
    ): FlowInterface {
        return $this->flow->start(
            context: $context,
            taskManager: $taskManager ?: $this->taskManagerFactory->detect(),
            callbacks: $callbacks,
            workersLimit: $workersLimit,
            socketServer: $socketServer
                ?: $this->socketService->createServer(
                    socketPath: $this->socketService->makeSocketPath()
                )
        );
    }
}
