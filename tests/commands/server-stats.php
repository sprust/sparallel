<?php

declare(strict_types=1);

use SParallel\Server\ManagerRpcClient;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../../vendor/autoload.php';

$rpc = TestContainer::resolve()->get(ManagerRpcClient::class);

/** @phpstan-ignore-next-line while.alwaysTrue */
while (true) {
    try {
        $stats = $rpc->stats();
    } catch (Throwable $exception) {
        system('clear');

        echo $exception->getMessage() . PHP_EOL;

        sleep(1);

        continue;
    }

    system('clear');

    dump($stats);

    sleep(1);
}
