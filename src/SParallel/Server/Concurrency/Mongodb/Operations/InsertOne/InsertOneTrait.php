<?php

declare(strict_types=1);

namespace SParallel\Server\Concurrency\Mongodb\Operations\InsertOne;

use SParallel\Exceptions\ConcurrencyResponseException;
use SParallel\SParallelConcurrency;

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

        SParallelConcurrency::continue();

        $runningOperation = $this->parseRunningOperationResponse($response);

        while (true) {
            $result = $this->insertOneResult($runningOperation->uuid);

            if (is_null($result)) {
                SParallelConcurrency::continue();

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
            throw new ConcurrencyResponseException(
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
