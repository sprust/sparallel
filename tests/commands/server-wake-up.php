<?php

declare(strict_types=1);

use SParallel\Server\ManagerRpcClient;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../../vendor/autoload.php';

$rpc = TestContainer::resolve()->get(ManagerRpcClient::class);

$start = microtime(true);

$totalTime = microtime(true) - $start;

$response = $rpc->wakeUp();

echo "Answer:\t\t$response->answer\n";
echo "TotalTime:\t$totalTime\n";
