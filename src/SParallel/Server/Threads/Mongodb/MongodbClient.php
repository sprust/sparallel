<?php

declare(strict_types=1);

namespace SParallel\Server\Threads\Mongodb;

use SParallel\Exceptions\RunningOperationException;
use SParallel\Server\Threads\Mongodb\Operations\Aggregate\AggregateTrait;
use SParallel\Server\Threads\Mongodb\Operations\BulkWrite\BulkWriteTrait;
use SParallel\Server\Threads\Mongodb\Operations\InsertOne\InsertOneTrait;
use SParallel\Server\Threads\Mongodb\Operations\RunningOperation;
use SParallel\Server\Threads\Mongodb\Operations\UpdateOne\UpdateOneTrait;
use SParallel\Server\Threads\Mongodb\Serialization\DocumentSerializer;
use Spiral\Goridge\RPC\RPC;

readonly class MongodbClient
{
    use InsertOneTrait;
    use UpdateOneTrait;
    use AggregateTrait;
    use BulkWriteTrait;

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
