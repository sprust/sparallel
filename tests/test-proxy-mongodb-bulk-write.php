<?php

declare(strict_types=1);

use MongoDB\BSON\UTCDateTime;
use SParallel\Contracts\MongodbConnectionUriFactoryInterface;
use SParallel\Server\Threads\Mongodb\MongodbClient;
use SParallel\Server\Threads\Mongodb\MongodbCollectionWrapper;
use SParallel\SParallelThreads;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

$serv = match ($_SERVER['argv'][1]) {
    'true' => true,
    'false' => false,
    default => throw new RuntimeException('Invalid server usage flag'),
};

$total             = (int) ($_SERVER['argv'][2] ?? 5);
$threadsLimitCount = (int) ($_SERVER['argv'][3] ?? 0);

$counter = $total;

/** @var array<Closure> $callbacks */
$callbacks = [];

$start = microtime(true);

$collection = new MongodbCollectionWrapper(
    uriFactory: TestContainer::resolve()->get(MongodbConnectionUriFactoryInterface::class),
    mongodbClient: TestContainer::resolve()->get(MongodbClient::class),
    databaseName: 'sparallel-test',
    collectionName: 'test',
    useServer: $serv,
);

while ($counter--) {
    $date = new UTCDateTime();

    $callbacks["bw-$counter"] = static fn() => $collection->bulkWrite(
        operations: [
            [
                'updateOne' => [
                    [
                        'uniquid'  => uniqid((string) $counter),
                        'upserted' => false,
                    ],
                    [
                        '$set'         => [
                            'dtStart' => $date,
                            'dtEnd'   => $date,
                        ],
                        '$setOnInsert' => [
                            'createdAt' => $date,
                        ],
                    ],
                ],
            ],
            [
                'updateOne' => [
                    [
                        'uniquid'  => uniqid((string) $counter),
                        'upserted' => true,
                    ],
                    [
                        '$set'         => [
                            'dtStart' => $date,
                            'dtEnd'   => $date,
                        ],
                        '$setOnInsert' => [
                            'createdAt' => $date,
                        ],
                    ],
                    [
                        'upsert' => true,
                    ],
                ],
            ],
            [
                'updateMany' => [
                    [
                        'uniquid'       => uniqid((string) $counter),
                        'upserted_many' => false,
                    ],
                    [
                        '$set'         => [
                            'dtStart' => $date,
                            'dtEnd'   => $date,
                        ],
                        '$setOnInsert' => [
                            'createdAt' => $date,
                        ],
                    ],
                ],
            ],
            [
                'updateMany' => [
                    [
                        'uniquid'       => uniqid((string) $counter),
                        'upserted_many' => true,
                    ],
                    [
                        '$set'         => [
                            'dtStart' => $date,
                            'dtEnd'   => $date,
                        ],
                        '$setOnInsert' => [
                            'createdAt' => $date,
                        ],
                    ],
                    [
                        'upsert' => true,
                    ],
                ],
            ],
            [
                'deleteOne' => [
                    [
                        'uniquid' => uniqid((string) $counter),
                    ],
                ],
            ],
            [
                'deleteMany' => [
                    [
                        'uniquid' => uniqid((string) $counter),
                    ],
                ],
            ],
            [
                'replaceOne' => [
                    [
                        'uniquid'  => uniqid((string) $counter),
                        'upserted' => false,
                    ],
                    [
                        'uniquid'  => uniqid((string) $counter) . '-upd',
                        'upserted' => true,
                    ],
                ],
            ],
            [
                'replaceOne' => [
                    [
                        'uniquid'  => uniqid((string) $counter),
                        'upserted' => true,
                    ],
                    [
                        'uniquid'  => uniqid((string) $counter) . '-upd',
                        'upserted' => true,
                    ],
                    [
                        'upsert' => true,
                    ],
                ],
            ],
        ]
    );
}

$threads = TestContainer::resolve()->get(SParallelThreads::class);

$insertedIds = [];

foreach ($threads->run($callbacks, $threadsLimitCount) as $key => $result) {
    echo "success: $key\n";
    print_r($result->result);
}

$totalTime = microtime(true) - $start;
$memPeak   = round(memory_get_peak_usage(true) / 1024 / 1024, 4);

echo "\n\nTotal call:\t$total\n";
echo "Thr limit:\t$threadsLimitCount\n";
echo "Mem peak:\t$memPeak\n";
echo "Total time:\t$totalTime\n";
