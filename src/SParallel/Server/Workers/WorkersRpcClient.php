<?php

declare(strict_types=1);

namespace SParallel\Server\Workers;

use SParallel\Contracts\RpcClientInterface;
use SParallel\Server\Dto\ResponseAnswer;
use SParallel\Server\Dto\ServerFinishedTask;
use Throwable;

readonly class WorkersRpcClient
{
    public function __construct(protected RpcClientInterface $rpcClient)
    {
    }

    /**
     * @throws Throwable
     */
    public function reload(): ResponseAnswer
    {
        $response = $this->rpcClient->call('WorkersServer.Reload', [
            'Message' => 'reload, please.',
        ]);

        return new ResponseAnswer(
            answer: $response['Answer']
        );
    }

    /**
     * @throws Throwable
     */
    public function addTask(
        string $groupUuid,
        string $taskUuid,
        string $payload,
        int $unixTimeout
    ): void {
        $this->rpcClient->call('WorkersServer.AddTask', [
            'GroupUuid'   => $groupUuid,
            'TaskUuid'    => $taskUuid,
            'UnixTimeout' => $unixTimeout,
            'Payload'     => $payload,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function detectAnyFinishedTask(string $groupUuid): ServerFinishedTask
    {
        $rpcResponse = $this->rpcClient->call('WorkersServer.DetectAnyFinishedTask', [
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

    /**
     * @throws Throwable
     */
    public function cancelGroup(string $groupUuid): void
    {
        $this->rpcClient->call('WorkersServer.CancelGroup', [
            'GroupUuid' => $groupUuid,
        ]);
    }
}
