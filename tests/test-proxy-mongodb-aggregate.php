<?php

declare(strict_types=1);

use SParallel\Server\Threads\Mongodb\MongodbClient;
use SParallel\SParallelThreads;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

$mongodbClient = TestContainer::resolve()->get(MongodbClient::class);

$total             = (int) ($_SERVER['argv'][1] ?? 5);
$threadsLimitCount = (int) ($_SERVER['argv'][2] ?? 0);

$counter = $total;

/** @var array<Closure> $callbacks */
$callbacks = [];

$start = microtime(true);

$connection = "mongodb://pms_admin:_sl_password_567@host.docker.internal:27078";
$database   = 'sparallel-test';
$collection = 'test';

while ($counter--) {
    $callbacks["agg-$counter"] = static fn() => $mongodbClient->aggregate(
        connection: $connection,
        database: $database,
        collection: $collection,
        pipeline: [
            [
                '$match' => [
                    'bool' => true,
                ]
            ]
        ]
    );
}

$threads = TestContainer::resolve()->get(SParallelThreads::class);

$insertedIds = [];

foreach ($threads->run($callbacks, $threadsLimitCount) as $key => $result) {
    foreach ($result->result as $rKey => $doc) {
        echo "document $key-$rKey:$doc->_id\n";
    }
}

$totalTime = microtime(true) - $start;
$memPeak   = round(memory_get_peak_usage(true) / 1024 / 1024, 4);

echo "\n\nTotal call:\t$total\n";
echo "Thr limit:\t$threadsLimitCount\n";
echo "Mem peak:\t$memPeak\n";
echo "Total time:\t$totalTime\n";
