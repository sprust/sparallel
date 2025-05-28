<?php

declare(strict_types=1);

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use SParallel\Server\Proxy\Mongodb\Operations\RunningOperation;
use SParallel\Server\Proxy\Mongodb\ProxyMongodbRpcClient;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

$rpc = TestContainer::resolve()->get(ProxyMongodbRpcClient::class);

$total = (int) $_SERVER['argv'][1];

$x = $total;

/** @var RunningOperation[] $operations */
$operations = [];

$start = microtime(true);

$connection = "mongodb://pms_admin:_sl_password_567@host.docker.internal:27078";
$database   = 'sparallel-test';
$collection = 'test';

while ($x--) {
    $operation = $rpc->insertOne(
        connection: $connection,
        database: $database,
        collection: $collection,
        document: [
            'uniq'      => uniqid(),
            'bool'      => true,
            'date'      => new UTCDateTime(),
            'dates'     => [
                new UTCDateTime(),
                new UTCDateTime(),
                'dates'     => [
                    new UTCDateTime(),
                    new UTCDateTime(),
                ],
                'dates_ass' => [
                    'one' => new UTCDateTime(),
                    'two' => new UTCDateTime(),
                ],
            ],
            'dates_ass' => [
                'one'       => new UTCDateTime(),
                'two'       => new UTCDateTime(),
                'dates'     => [
                    new UTCDateTime(),
                    new UTCDateTime(),
                ],
                'dates_ass' => [
                    'one' => new UTCDateTime(),
                    'two' => new UTCDateTime(),
                ],
            ],
        ]
    );

    $operations[] = $operation;
}

$startWaiting = microtime(true);

/** @var array<string> $insertedIds */
$insertedIds = [];

while (count($operations) > 0) {
    $operationKeys = array_keys($operations);

    foreach ($operationKeys as $operationKey) {
        $operation = $operations[$operationKey];

        if ($operation->error) {
            echo "op error: $operation->error\n";

            unset($operations[$operationKey]);

            continue;
        }

        $result = $rpc->insertOneResult($operation->uuid);

        if (!$result->isFinished) {
            continue;
        }

        if ($result->error) {
            echo "res error: $result->error\n";

            unset($operations[$operationKey]);

            continue;
        }

        unset($operations[$operationKey]);

        $insertedIds[] = (string) $result->result->insertedId;

        echo "success:\n";
        print_r($result->result);
    }
}

foreach ($insertedIds as $insertedId) {
    var_dump($insertedId);

    $operation = $rpc->updateOne(
        connection: $connection,
        database: $database,
        collection: $collection,
        filter: [
            '_id' => new ObjectId($insertedId),
        ],
        update: [
            '$set' => [
                'upd' => uniqid(),
            ],
        ]
    );

    $operations[] = $operation;

    $operation = $rpc->updateOne(
        connection: $connection,
        database: $database,
        collection: $collection,
        filter: [
            '_id' => 111,
        ],
        update: [
            '$set' => [
                'upserted' => uniqid(),
            ],
        ],
        opUpsert: true
    );

    $operations[] = $operation;
}

while (count($operations) > 0) {
    $operationKeys = array_keys($operations);

    foreach ($operationKeys as $operationKey) {
        $operation = $operations[$operationKey];

        if ($operation->error) {
            echo "op error: $operation->error\n";

            unset($operations[$operationKey]);

            continue;
        }

        $result = $rpc->updateOneResult($operation->uuid);

        if (!$result->isFinished) {
            continue;
        }

        if ($result->error) {
            echo "res error: $result->error\n";

            unset($operations[$operationKey]);

            continue;
        }

        unset($operations[$operationKey]);

        echo "success:\n";
        print_r($result->result);
    }
}

$waitingTime = microtime(true) - $startWaiting;
$totalTime   = microtime(true) - $start;

echo "\n\nWaitingTime:\t$waitingTime\nTotalTime:\t$totalTime\n";
