<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb\Operations\InsertOne;

use SParallel\Server\Proxy\Mongodb\Operations\RunningOperation;

trait InsertOneTrait
{
    /**
     * @param array<int|string, mixed> $document
     */
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
            'Document'   => $this->documentSerializer->serialize($document),
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

        if ($rawResult = ($response['Result'] ?: null)) {
            $docResult = $this->documentSerializer->unserialize($rawResult)->toPHP();

            $result = new InsertOneResult(
                insertedId: $docResult->insertedid,
            );
        }

        return new InsertOneResultReply(
            isFinished: $response['IsFinished'],
            error: $response['Error'],
            result: $result
        );
    }
}
