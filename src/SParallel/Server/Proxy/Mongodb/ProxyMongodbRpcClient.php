<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb;

use SParallel\Server\Proxy\Mongodb\Operations\InsertOne\InsertOneTrait;
use SParallel\Server\Proxy\Mongodb\Operations\UpdateOne\UpdateOneTrait;
use SParallel\Server\Proxy\Mongodb\Serialization\DocumentSerializer;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\RPC;

readonly class ProxyMongodbRpcClient
{
    use InsertOneTrait;
    use UpdateOneTrait;

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
