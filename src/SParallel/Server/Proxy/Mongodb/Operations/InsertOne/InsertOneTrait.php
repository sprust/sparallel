<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb\Operations\InsertOne;

use SParallel\SParallelThreads;

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
    ): InsertOneResultReply {
        $response = $this->rpc->call("ProxyMongodbServer.InsertOne", [
            'Connection' => $connection,
            'Database'   => $database,
            'Collection' => $collection,
            'Document'   => $this->documentSerializer->serialize($document),
        ]);

        SParallelThreads::continue();

        $runningOperation = $this->parseRunningOperationResponse($response);

        while (true) {
            $result = $this->insertOneResult($runningOperation->uuid);

            if (!$result->isFinished) {
                SParallelThreads::continue();

                continue;
            }

            return $result;
        }
    }

    protected function insertOneResult(string $operationUuid): InsertOneResultReply
    {
        $response = $this->rpc->call("ProxyMongodbServer.InsertOneResult", [
            'OperationUuid' => $operationUuid,
        ]);

        $result = null;

        if ($rawResult = ($response['Result'] ?: null)) {
            $docResult = (array) $this->documentSerializer->unserialize($rawResult)->toPHP();

            $result = new InsertOneResult(
                insertedId: $docResult['insertedid'],
            );
        }

        return new InsertOneResultReply(
            isFinished: $response['IsFinished'],
            error: $response['Error'],
            result: $result
        );
    }
}
