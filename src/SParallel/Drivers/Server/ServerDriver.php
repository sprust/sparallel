<?php

declare(strict_types=1);

namespace SParallel\Drivers\Server;

use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Entities\Context;
use SParallel\Server\Workers\WorkersRpcClient;
use SParallel\Transport\ServerTaskTransport;
use SParallel\Transport\TaskResultTransport;

readonly class ServerDriver implements DriverInterface
{
    public const DRIVER_NAME = 'server';

    public function __construct(
        protected WorkersRpcClient $rpcClient,
        protected ServerTaskTransport $serverTaskTransport,
        protected TaskResultTransport $taskResultTransport,
        protected EventsBusInterface $eventsBus,
        protected CallbackCallerInterface $callbackCaller,
    ) {
    }

    public function run(
        Context $context,
        array &$callbacks,
        int $timeoutSeconds,
        int $workersLimit
    ): WaitGroupInterface {
        return new ServerWaitGroup(
            context: $context,
            callbacks: $callbacks,
            timeoutSeconds: $timeoutSeconds,
            workersLimit: $workersLimit,
            rpcClient: $this->rpcClient,
            serverTaskTransport: $this->serverTaskTransport,
            taskResultTransport: $this->taskResultTransport,
            eventsBus: $this->eventsBus,
            callbackCaller: $this->callbackCaller,
        );
    }
}
