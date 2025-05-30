<?php

declare(strict_types=1);

namespace SParallel\Server\Threads\Mongodb\Operations\BulkWrite;

use SParallel\Exceptions\InvalidMongodbBulkWriteOperationException;
use SParallel\Exceptions\ThreadResponseException;
use SParallel\SParallelThreads;

trait BulkWriteTrait
{
    /**
     * @param array<int|string, mixed> $operations
     */
    public function bulkWrite(
        string $connection,
        string $database,
        string $collection,
        array $operations,
    ): BulkWriteResult {
        $response = $this->rpc->call("ProxyMongodbServer.BulkWrite", [
            'Connection' => $connection,
            'Database'   => $database,
            'Collection' => $collection,
            'Models'     => $this->documentSerializer->serialize(
                document: $this->prepareOperations($operations)
            ),
        ]);

        SParallelThreads::continue();

        $operationUuid = $this->parseRunningOperationResponse($response)->uuid;

        while (true) {
            $result = $this->bulkWriteResult($operationUuid);

            if (is_null($result)) {
                SParallelThreads::continue();

                continue;
            }

            $docResult = (array) $this->documentSerializer->unserialize($result)->toPHP();

            return new BulkWriteResult(
                insertedCount: (int) $docResult['insertedcount'],
                matchedCount: (int) $docResult['matchedcount'],
                modifiedCount: (int) $docResult['modifiedcount'],
                deletedCount: (int) $docResult['deletedcount'],
                upsertedCount: (int) $docResult['upsertedcount'],
                upsertedIds: (array) $docResult['upsertedids'],
            );
        }
    }

    protected function bulkWriteResult(string $operationUuid): ?string
    {
        $response = $this->rpc->call("ProxyMongodbServer.BulkWriteResult", [
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

        return $response['Result'];
    }

    /**
     * @param array<int, mixed> $operations
     *
     * @return array<int, array{type: string, model: array<string, mixed>}>
     */
    protected function prepareOperations(array $operations): array
    {
        $result = [];

        foreach ($operations as $operation) {
            $type  = array_key_first($operation);
            $value = $operation[$type];

            // TODO: value validation
            $result[] = [
                'type'  => $type,
                'model' => match ($type) {
                    'insertOne' => [
                        'document' => $value,
                    ],
                    'updateOne', 'updateMany' => [
                        'filter' => $value[0],
                        'update' => $value[1],
                        'upsert' => $value[2]['upsert'] ?? false,
                    ],
                    'deleteOne', 'deleteMany' => [
                        'filter' => $value,
                    ],
                    'replaceOne' => [
                        'filter'      => $value[0],
                        'replacement' => $value[1],
                        'upsert'      => $value[2]['upsert'] ?? false,
                    ],
                    default => throw new InvalidMongodbBulkWriteOperationException(
                        operationType: (string) $operation
                    )
                },
            ];
        }

        return $result;
    }
}
