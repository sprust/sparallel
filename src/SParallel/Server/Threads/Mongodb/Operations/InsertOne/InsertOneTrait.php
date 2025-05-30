<?php

declare(strict_types=1);

namespace SParallel\Server\Threads\Mongodb\Operations\InsertOne;

use SParallel\Exceptions\ThreadResponseException;
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
    ): InsertOneResult {
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

            if (is_null($result)) {
                SParallelThreads::continue();

                continue;
            }

            return $result;
        }
    }

    protected function insertOneResult(string $operationUuid): ?InsertOneResult
    {
        $response = $this->rpc->call("ProxyMongodbServer.InsertOneResult", [
            'OperationUuid' => $operationUuid,
        ]);

        if ($error = $response['Error']) {
            throw new ThreadResponseException(
                message: $error,
            );
        }

        if (!$response['IsFinished']) {
            return null;
        }

        $docResult = (array) $this->documentSerializer->unserialize($response['Result'])->toPHP();

        return new InsertOneResult(
            insertedId: $docResult['insertedid'],
        );
    }
}
