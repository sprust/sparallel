<?php

declare(strict_types=1);

use MongoDB\BSON\UTCDateTime;
use SParallel\Server\Proxy\Mongodb\MongodbProxy;
use SParallel\SParallelThreads;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

$proxy = TestContainer::resolve()->get(MongodbProxy::class);

$total = (int) $_SERVER['argv'][1];

$x = $total;

/** @var array<Closure> $callbacks */
$callbacks = [];

$start = microtime(true);

$connection = "mongodb://pms_admin:_sl_password_567@host.docker.internal:27078";
$database   = 'sparallel-test';
$collection = 'test';

while ($x--) {
    $callbacks["insert-$x"] = static fn() => $proxy->insertOne(
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
    )->result->insertedId;
}

$threads = TestContainer::resolve()->get(SParallelThreads::class);

$insertedIds = [];

foreach ($threads->run($callbacks) as $key => $result) {
    if ($result->exception) {
        echo "$key: ERROR: {$result->exception->getMessage()}\n";

        continue;
    }

    $insertedIds[$key] = $result->result;

    echo "success:\n";
    print_r($result->result);
}

foreach ($insertedIds as $key => $insertedId) {
    $callbacks["upd-$key-real"] = static fn() => $proxy->updateOne(
        connection: $connection,
        database: $database,
        collection: $collection,
        filter: [
            '_id' => $insertedId,
        ],
        update: [
            '$set' => [
                'upd' => uniqid(),
            ],
        ]
    );

    $callbacks["upd-$key-upsert"] = static fn() => $proxy->updateOne(
        connection: $connection,
        database: $database,
        collection: $collection,
        filter: [
            '_id' => uniqid(),
        ],
        update: [
            '$set' => [
                'upserted' => uniqid(),
            ],
        ],
        opUpsert: true
    );
}

foreach ($threads->run($callbacks) as $key => $result) {
    if ($result->exception) {
        echo "$key: ERROR: {$result->exception->getMessage()}\n";

        continue;
    }

    echo "success:\n";
    print_r($result->result);
}

$totalTime = microtime(true) - $start;
$memPeak   = round(memory_get_peak_usage(true) / 1024 / 1024, 4);

echo "\n\nMemPeak:\t$memPeak\n";
echo "TotalTime:\t$totalTime\n";
