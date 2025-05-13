<?php

declare(strict_types=1);

use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\TestsImplementation\TestContainer;

include __DIR__ . '/../vendor/autoload.php';

$x = 100;
$binFilePath = __DIR__ . '/../bin/proc_coll';

$processService = TestContainer::resolve()->get(ProcessService::class);
$socketService = TestContainer::resolve()->get(SocketService::class);

$start = microtime(true);

while ($x--) {
    $socketFilePath = $socketService->makeSocketPath();

    $command = "$binFilePath $socketFilePath " . time() + 3 . " >> /dev/null 2>&1 &";

    exec($command, $output, $exitCode);
}

echo round(microtime(true) - $start, 6) . PHP_EOL;;
