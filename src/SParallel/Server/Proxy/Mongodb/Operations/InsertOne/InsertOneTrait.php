<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb\Operations\InsertOne;

use MongoDB\BSON\ObjectId;
use SParallel\Server\Proxy\Mongodb\Operations\RunningOperation;

trait InsertOneTrait
{
    public function insertOne(
        string $connection,
        string $database,
        string $collection,
        array $document,
    ): RunningOperation {
        $response = $this->rpc->call("ProxyMongodbServer.InsertOne", [
            'Connection' => $connection,
            'Database'   => $database,
            'Collection' => $collection,
            'Document'   => json_encode($document),
        ]);

        return new RunningOperation(
            error: $response['Error'],
            uuid: $response['OperationUuid']
        );
    }

    public function insertOneResult(string $operationUuid): InsertOneResultReply
    {
        $response = $this->rpc->call("ProxyMongodbServer.InsertOneResult", [
            'OperationUuid' => $operationUuid,
        ]);

        $result = null;

        if (array_key_exists('Result', $response)) {
            $result = new InsertOneResult(
                insertedId: new ObjectId(
                    id: $response['Result']['InsertedID']
                ),
            );
        }

        return new InsertOneResultReply(
            isFinished: $response['IsFinished'],
            error: $response['Error'],
            result: $result
        );
    }
}
