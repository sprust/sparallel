<?php

declare(strict_types=1);

namespace SParallel\Drivers\Server\Rpc;

use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\RPC;

readonly class ServerRpcClient
{
    protected RPC $rpc;

    public function __construct(string $host, int $port)
    {
        $this->rpc = new RPC(
            Relay::create("tcp://$host:$port")
        );
    }

    public function addTask(
        string $groupUuid,
        string $taskUuid,
        string $payload,
        int $unixTimeout
    ): void {
        $this->rpc->call("WorkersServer.AddTask", [
            'GroupUuid'   => $groupUuid,
            'TaskUuid'    => $taskUuid,
            'UnixTimeout' => $unixTimeout,
            'Payload'     => $payload,
        ]);
    }

    public function detectAnyFinishedTask(string $groupUuid): FinishedTask
    {
        $rpcResponse = $this->rpc->call("WorkersServer.DetectAnyFinishedTask", [
            'GroupUuid' => $groupUuid,
        ]);

        return new FinishedTask(
            groupUuid: $rpcResponse['GroupUuid'],
            taskUuid: $rpcResponse['TaskUuid'],
            isFinished: $rpcResponse['IsFinished'],
            response: $rpcResponse['Response'],
            isError: $rpcResponse['IsError'],
        );
    }

    public function cancelGroup(string $groupUuid): void
    {
        $this->rpc->call("WorkersServer.CancelGroup", [
            'GroupUuid' => $groupUuid,
        ]);
    }
}
