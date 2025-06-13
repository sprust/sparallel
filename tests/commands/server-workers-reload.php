<?php

declare(strict_types=1);

use SParallel\Server\Workers\WorkersRpcClient;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../../vendor/autoload.php';

$rpc = TestContainer::resolve()->get(WorkersRpcClient::class);

$start = microtime(true);

$totalTime = microtime(true) - $start;

$response = $rpc->reload();

echo "Answer:\t\t$response->answer\n";
echo "TotalTime:\t$totalTime\n";
