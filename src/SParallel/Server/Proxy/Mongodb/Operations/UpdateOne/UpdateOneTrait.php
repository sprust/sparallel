<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb\Operations\UpdateOne;

use SParallel\SParallelThreads;

trait UpdateOneTrait
{
    /**
     * @param array<int|string, mixed> $filter
     * @param array<int|string, mixed> $update
     */
    public function updateOne(
        string $connection,
        string $database,
        string $collection,
        array $filter,
        array $update,
        bool $opUpsert = false,
    ): UpdateOneResultReply {
        $response = $this->rpc->call("ProxyMongodbServer.UpdateOne", [
            'Connection' => $connection,
            'Database'   => $database,
            'Collection' => $collection,
            'Filter'     => $this->documentSerializer->serialize($filter),
            'Update'     => $this->documentSerializer->serialize($update),
            'OpUpsert'   => $opUpsert,
        ]);

        $runningOperation = $this->parseRunningOperationResponse($response);

        while (true) {
            $result = $this->updateOneResult($runningOperation->uuid);

            if (!$result->isFinished) {
                SParallelThreads::continue();

                continue;
            }

            return $result;
        }
    }

    protected function updateOneResult(string $operationUuid): UpdateOneResultReply
    {
        $response = $this->rpc->call("ProxyMongodbServer.UpdateOneResult", [
            'OperationUuid' => $operationUuid,
        ]);

        $result = null;

        if ($rawResult = ($response['Result'] ?: null)) {
            $docResult = (array) $this->documentSerializer->unserialize($rawResult)->toPHP();

            $result = new UpdateOneResult(
                matchedCount: (int) $docResult['matchedcount'],
                modifiedCount: (int) $docResult['modifiedcount'],
                upsertedCount: (int) $docResult['upsertedcount'],
                upsertedId: $docResult['upsertedid'],
            );
        }

        return new UpdateOneResultReply(
            isFinished: $response['IsFinished'],
            error: $response['Error'],
            result: $result
        );
    }
}
