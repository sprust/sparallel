<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb\Operations\Aggregate;

use SParallel\SParallelThreads;

trait AggregateTrait
{
    /**
     * @param array<int|string, mixed> $pipeline
     */
    public function aggregate(
        string $connection,
        string $database,
        string $collection,
        array $pipeline,
    ): AggregateResultReply {
        $response = $this->rpc->call("ProxyMongodbServer.Aggregate", [
            'Connection' => $connection,
            'Database'   => $database,
            'Collection' => $collection,
            'Pipeline'   => $this->documentSerializer->serialize($pipeline),
        ]);

        SParallelThreads::continue();

        $runningOperation = $this->parseRunningOperationResponse($response);

        while (true) {
            $result = $this->aggregateResult($runningOperation->uuid);

            if (!$result->isFinished) {
                SParallelThreads::continue();

                continue;
            }

            // TODO: generator or iterator

            return $result;
        }
    }

    protected function aggregateResult(string $operationUuid): AggregateResultReply
    {
        $response = $this->rpc->call("ProxyMongodbServer.AggregateResult", [
            'OperationUuid' => $operationUuid,
        ]);

        $result = null;

        if ($rawResult = ($response['Result'] ?: null)) {
            $result = $this->documentSerializer->unserialize($rawResult);
        }

        return new AggregateResultReply(
            isFinished: $response['IsFinished'],
            error: $response['Error'],
            result: $result,
            nextUuid: $response['NextUuid'],
        );
    }
}
