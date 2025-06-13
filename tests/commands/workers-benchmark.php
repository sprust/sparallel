<?php

declare(strict_types=1);

ini_set('memory_limit', '1G');

use SParallel\Drivers\Server\ServerDriver;
use SParallel\TestCases\Benchmark;
use SParallel\TestsImplementation\TestContainer;
use SParallel\TestsImplementation\TestEventsRepository;
use SParallel\TestsImplementation\TestLogger;

require_once __DIR__ . '/../../vendor/autoload.php';

$benchmark = new Benchmark(
    uniqueCount: 5,
    bigResponseCount: 5,
    sleepCount: 5,
    sleepSec: 1,
    memoryLimitCount: 5,
    throwCount: 5,
);

$driverClasses = [
    ServerDriver::class,
];

$timeoutSeconds = 5;
$workersLimit   = 10;

$container = TestContainer::resolve();

$container->get(TestEventsRepository::class)->flush();

TestLogger::flush();

$benchmark->start(
    container: $container,
    driverClasses: $driverClasses,
    timeoutSeconds: $timeoutSeconds,
    workersLimit: $workersLimit,
);

exit(0);
