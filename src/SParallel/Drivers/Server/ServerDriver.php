<?php

declare(strict_types=1);

namespace SParallel\Drivers\Server;

use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Server\Rpc\ServerRpcClient;
use SParallel\Services\Context;
use SParallel\Transport\ServerTaskTransport;
use SParallel\Transport\TaskResultTransport;

readonly class ServerDriver implements DriverInterface
{
    public const DRIVER_NAME = 'server';

    public function __construct(
        protected ServerRpcClient $rpcClient,
        protected ServerTaskTransport $serverTaskTransport,
        protected TaskResultTransport $taskResultTransport,
    ) {
    }

    public function run(Context $context, array &$callbacks, int $timeoutSeconds): WaitGroupInterface
    {
        return new ServerWaitGroup(
            context: $context,
            callbacks: $callbacks,
            timeoutSeconds: $timeoutSeconds,
            rpcClient: $this->rpcClient,
            serverTaskTransport: $this->serverTaskTransport,
            taskResultTransport: $this->taskResultTransport,
        );
    }
}
