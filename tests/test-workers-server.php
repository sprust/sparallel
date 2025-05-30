<?php

declare(strict_types=1);

use SParallel\Server\Workers\WorkersRpcClient;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

$rpc = TestContainer::resolve()->get(WorkersRpcClient::class);

$groupUuid = uniqid();

$total = (int) $_SERVER['argv'][1];

$x = $total;

$expected = [];

$start = microtime(true);

while ($x--) {
    $taskUuid = uniqid(prefix: "$x-", more_entropy: true);

    $rpc->addTask(
        groupUuid: $groupUuid,
        taskUuid: $taskUuid,
        payload: "Hello from SParallel [$x]",
        unixTimeout: time() + 5,
    );

    $expected[$taskUuid] = true;

    echo "Task [$taskUuid] added\n";
}

$startWaiting = microtime(true);

while ($total) {
    $response = $rpc->detectAnyFinishedTask($groupUuid);

    if (!$response->isFinished) {
        sleep(1);

        continue;
    }

    if (array_key_exists($response->taskUuid, $expected)) {
        unset($expected[$response->taskUuid]);
    } else {
        throw new RuntimeException("Unexpected task [$response->taskUuid]");
    }

    --$total;
}

$waitingTime = microtime(true) - $startWaiting;
$totalTime   = microtime(true) - $start;

echo "\n\nWaitingTime:\t$waitingTime\nTotalTime:\t$totalTime\n";
