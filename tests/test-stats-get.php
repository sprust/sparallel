<?php

declare(strict_types=1);

use SParallel\Server\StatsRpcClient;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

$rpc = TestContainer::resolve()->get(StatsRpcClient::class);

$start = microtime(true);

$totalTime   = microtime(true) - $start;

while (true) {
    try {
        $json = $rpc->get();
    } catch (Throwable $exception) {
        system('clear');

        echo $exception->getMessage() . PHP_EOL;

        sleep(1);

        continue;
    }

    system('clear');

    dump(json_decode($json, true));

    sleep(1);
}
