<?php

namespace SParallel\Implementation;

use SParallel\Contracts\RpcClientInterface;
use SParallel\Exceptions\RpcCallException;
use Spiral\Goridge\RPC\RPC;
use Throwable;

class RpcClient implements RpcClientInterface
{
    public function __construct(protected RPC $rpc)
    {
    }

    public function call(string $method, array $params = []): array
    {
        try {
            return $this->rpc->call($method, $params);
        } catch (Throwable $exception) {
            throw new RpcCallException($exception);
        }
    }
}
