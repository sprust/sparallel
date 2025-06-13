<?php

declare(strict_types=1);

use SParallel\Server\ManagerRpcClient;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../../vendor/autoload.php';

$rpc = TestContainer::resolve()->get(ManagerRpcClient::class);

$start = microtime(true);

$totalTime   = microtime(true) - $start;

/** @phpstan-ignore-next-line while.alwaysTrue */
while (true) {
    try {
        $json = $rpc->stats();
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
