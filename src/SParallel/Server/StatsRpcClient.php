<?php

declare(strict_types=1);

namespace SParallel\Server;

use SParallel\Contracts\RpcClientInterface;
use Throwable;

readonly class StatsRpcClient
{
    public function __construct(protected RpcClientInterface $rpcClient)
    {
    }

    /**
     * @throws Throwable
     */
    public function get(): string
    {
        $response = $this->rpcClient->call('StatsServer.Get', [
            'Message' => 'get stats, please.',
        ]);

        return $response['Json'];
    }
}
