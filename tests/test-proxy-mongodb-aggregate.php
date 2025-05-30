<?php

declare(strict_types=1);

use SParallel\Contracts\MongodbConnectionUriFactoryInterface;
use SParallel\Server\Threads\Mongodb\MongodbClient;
use SParallel\Server\Threads\Mongodb\MongodbCollectionWrapper;
use SParallel\SParallelThreads;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

$total             = (int) ($_SERVER['argv'][1] ?? 5);
$threadsLimitCount = (int) ($_SERVER['argv'][2] ?? 0);

$counter = $total;

/** @var array<Closure> $callbacks */
$callbacks = [];

$start = microtime(true);

$collection = new MongodbCollectionWrapper(
    uriFactory: TestContainer::resolve()->get(MongodbConnectionUriFactoryInterface::class),
    mongodbClient: TestContainer::resolve()->get(MongodbClient::class),
    databaseName: 'sparallel-test',
    collectionName: 'test',
    useServer: true,
);

while ($counter--) {
    $callbacks["agg-$counter"] = static fn() => $collection->aggregate(
        pipeline: [
            [
                '$match' => [
                    'bool' => true,
                ],
            ],
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
