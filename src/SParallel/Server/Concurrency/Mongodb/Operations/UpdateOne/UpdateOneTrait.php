<?php

declare(strict_types=1);

namespace SParallel\Server\Concurrency\Mongodb\Operations\UpdateOne;

use SParallel\Exceptions\ConcurrencyResponseException;
use SParallel\SParallelConcurrency;

trait UpdateOneTrait
{
    /**
     * @param array<int|string, mixed> $filter
     * @param array<int|string, mixed> $update
     * @param array<string, mixed>     $options
     */
    public function updateOne(
        string $connection,
        string $database,
        string $collection,
        array $filter,
        array $update,
        array $options,
    ): UpdateOneResult {
        $response = $this->rpc->call("ProxyMongodbServer.UpdateOne", [
            'Connection' => $connection,
            'Database'   => $database,
            'Collection' => $collection,
            'Filter'     => $this->documentSerializer->serialize($filter),
            'Update'     => $this->documentSerializer->serialize($update),
            'OpUpsert'   => $options['upsert'] ?? false, // TODO
        ]);

        SParallelConcurrency::continue();

        $runningOperation = $this->parseRunningOperationResponse($response);

        while (true) {
            $result = $this->updateOneResult($runningOperation->uuid);

            if (is_null($result)) {
                SParallelConcurrency::continue();

                continue;
            }

            return $result;
        }
    }

    protected function updateOneResult(string $operationUuid): ?UpdateOneResult
    {
        $response = $this->rpc->call("ProxyMongodbServer.UpdateOneResult", [
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

        return new UpdateOneResult(
            matchedCount: (int) $docResult['matchedcount'],
            modifiedCount: (int) $docResult['modifiedcount'],
            upsertedCount: (int) $docResult['upsertedcount'],
            upsertedId: $docResult['upsertedid'],
        );
    }
}
