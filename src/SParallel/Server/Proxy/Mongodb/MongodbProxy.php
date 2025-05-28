<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb;

use SParallel\Exceptions\RunningOperationException;
use SParallel\Server\Proxy\Mongodb\Operations\Aggregate\AggregateTrait;
use SParallel\Server\Proxy\Mongodb\Operations\InsertOne\InsertOneTrait;
use SParallel\Server\Proxy\Mongodb\Operations\RunningOperation;
use SParallel\Server\Proxy\Mongodb\Operations\UpdateOne\UpdateOneTrait;
use SParallel\Server\Proxy\Mongodb\Serialization\DocumentSerializer;
use Spiral\Goridge\RPC\RPC;

readonly class MongodbProxy
{
    use InsertOneTrait;
    use UpdateOneTrait;
    use AggregateTrait;

    public function __construct(
        protected RPC $rpc,
        protected DocumentSerializer $documentSerializer,
    ) {
    }

    /**
     * @param array<string, mixed> $response
     */
    protected function parseRunningOperationResponse(array $response): RunningOperation
    {
        $operation = new RunningOperation(
            error: $response['Error'],
            uuid: $response['OperationUuid']
        );

        if ($operation->error) {
            throw new RunningOperationException(
                message: $operation->error,
            );
        }

        return $operation;
    }
}
