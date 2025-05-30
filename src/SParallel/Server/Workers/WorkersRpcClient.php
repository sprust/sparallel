<?php

declare(strict_types=1);

namespace SParallel\Server\Workers;

use Spiral\Goridge\RPC\RPC;

readonly class WorkersRpcClient
{
    public function __construct(protected RPC $rpc)
    {
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

    public function detectAnyFinishedTask(string $groupUuid): ServerFinishedTask
    {
        $rpcResponse = $this->rpc->call("WorkersServer.DetectAnyFinishedTask", [
            'GroupUuid' => $groupUuid,
        ]);

        return new ServerFinishedTask(
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
