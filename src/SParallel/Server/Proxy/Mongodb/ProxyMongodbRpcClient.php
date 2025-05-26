<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb;

use SParallel\Server\Proxy\Mongodb\Operations\InsertOne\InsertOneTrait;
use SParallel\Server\Proxy\Mongodb\Serializers\DocumentSerializer;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\RPC;

readonly class ProxyMongodbRpcClient
{
    use InsertOneTrait;

    protected RPC $rpc;

    public function __construct(
        string $host,
        int $port,
        protected DocumentSerializer $documentSerializer,
    ) {
        $this->rpc = new RPC(
            Relay::create("tcp://$host:$port")
        );
    }
}
