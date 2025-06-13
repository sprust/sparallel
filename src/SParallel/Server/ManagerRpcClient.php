<?php

declare(strict_types=1);

namespace SParallel\Server;

use SParallel\Contracts\RpcClientInterface;
use SParallel\Server\Dto\ResponseAnswer;
use Throwable;

readonly class ManagerRpcClient
{
    public function __construct(protected RpcClientInterface $rpcClient)
    {
    }

    /**
     * @throws Throwable
     */
    public function sleep(): ResponseAnswer
    {
        $response = $this->rpcClient->call('ManagerServer.Sleep', [
            'Message' => 'sleep, please.',
        ]);

        return new ResponseAnswer(
            answer: $response['Answer']
        );
    }

    /**
     * @throws Throwable
     */
    public function wakeUp(): ResponseAnswer
    {
        $response = $this->rpcClient->call('ManagerServer.WakeUp', [
            'Message' => 'wake up, please.',
        ]);

        return new ResponseAnswer(
            answer: $response['Answer']
        );
    }

    /**
     * @throws Throwable
     */
    public function stop(): ResponseAnswer
    {
        $response = $this->rpcClient->call('ManagerServer.Stop', [
            'Message' => 'stop, please.',
        ]);

        return new ResponseAnswer(
            answer: $response['Answer']
        );
    }

    /**
     * Return JSON
     *
     * @throws Throwable
     */
    public function stats(): string
    {
        $response = $this->rpcClient->call('ManagerServer.Stats', [
            'Message' => 'get stats, please.',
        ]);

        return $response['Json'];
    }
}
