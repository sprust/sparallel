<?php

declare(strict_types=1);

namespace SParallel\Server\Concurrency\Mongodb\Operations\Aggregate;

use Generator;
use MongoDB\BSON\Document;
use MongoDB\BSON\PackedArray;
use SParallel\Exceptions\ConcurrencyResponseException;
use SParallel\SParallelConcurrency;

trait AggregateTrait
{
    private const RESULT_KEY = '_result';

    /**
     * @param array<int, array<string, mixed>> $pipeline
     *
     * @return Generator<int, Document>
     */
    public function aggregate(
        string $connection,
        string $database,
        string $collection,
        array $pipeline,
    ): Generator {
        $response = $this->rpc->call("ProxyMongodbServer.Aggregate", [
            'Connection' => $connection,
            'Database'   => $database,
            'Collection' => $collection,
            'Pipeline'   => $this->documentSerializer->serialize($pipeline),
        ]);

        SParallelConcurrency::continue();

        $operationUuid = $this->parseRunningOperationResponse($response)->uuid;

        while ($operationUuid) {
            $result = $this->aggregateResult($operationUuid);

            if (is_null($result)) {
                SParallelConcurrency::continue();

                continue;
            }

            $items         = $result[0];
            $operationUuid = $result[1];

            if (iterator_count($items) === 0) {
                break;
            }

            foreach ($items as $key => $item) {
                yield $key => $item;
            }
        }
    }

    /**
     * @return array{0: PackedArray, 1: string}|null
     */
    protected function aggregateResult(string $operationUuid): ?array
    {
        $response = $this->rpc->call("ProxyMongodbServer.AggregateResult", [
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

        $rawResult         = $response['Result'];
        $nextOperationUuid = $response['NextUuid'];

        $document = $this->documentSerializer->unserialize($rawResult);

        if ($document->has(self::RESULT_KEY)) {
            return [$document->get(self::RESULT_KEY), $nextOperationUuid];
        }

        return [PackedArray::fromPHP([]), ""];
    }
}
