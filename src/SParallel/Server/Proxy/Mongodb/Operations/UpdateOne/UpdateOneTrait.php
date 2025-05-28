<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb\Operations\UpdateOne;

use SParallel\Server\Proxy\Mongodb\Operations\RunningOperation;

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
    ): RunningOperation {
        $response = $this->rpc->call("ProxyMongodbServer.UpdateOne", [
            'Connection' => $connection,
            'Database'   => $database,
            'Collection' => $collection,
            'Filter'     => $this->documentSerializer->serialize($filter),
            'Update'     => $this->documentSerializer->serialize($update),
            'OpUpsert'   => $opUpsert,
        ]);

        return new RunningOperation(
            error: $response['Error'],
            uuid: $response['OperationUuid']
        );
    }

    public function updateOneResult(string $operationUuid): UpdateOneResultReply
    {
        $response = $this->rpc->call("ProxyMongodbServer.UpdateOneResult", [
            'OperationUuid' => $operationUuid,
        ]);

        $result = null;

        if ($rawResult = ($response['Result'] ?: null)) {
            $docResult = $this->documentSerializer->unserialize($rawResult)->toPHP();

            var_dump($rawResult);

            $result = new UpdateOneResult(
                matchedCount: (int) $docResult->matchedcount,
                modifiedCount: (int) $docResult->modifiedcount,
                upsertedCount: (int) $docResult->upsertedcount,
                upsertedId: $docResult->upsertedid,
            );
        }

        return new UpdateOneResultReply(
            isFinished: $response['IsFinished'],
            error: $response['Error'],
            result: $result
        );
    }
}
