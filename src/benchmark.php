<?php

declare(strict_types=1);

ini_set('memory_limit', '1G');

use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Hybrid\HybridDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Services\SParallelService;
use SParallel\Tests\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @var array<mixed, Closure> $callbacks
 */
$callbacks = [
    ...makeCaseUnique(5),
    ...makeCaseBigResponse(5),
    ...makeCaseMemoryLimit(5),
    ...makeCaseSleep(count: 5, sec: 1),
    ...makeCaseThrow(5),
];

shuffle($callbacks);

$callbacksCount = count($callbacks);

/** @var array<class-string<\SParallel\Contracts\DriverInterface>> $driverClasses */
$driverClasses = [
    ForkDriver::class,
    ProcessDriver::class,
    HybridDriver::class,
];

$metrics = [];

foreach ($driverClasses as $driverClass) {
    echo '------------------------------------------' . PHP_EOL;
    echo 'Driver: ' . $driverClass . PHP_EOL;
    echo '------------------------------------------' . PHP_EOL;

    $start = microtime(true);

    $clonedCallbacks = array_merge($callbacks);

    $service = new SParallelService(
        driver: TestContainer::resolve()->get($driverClass),
        eventsBus: TestContainer::resolve()->get(EventsBusInterface::class),
    );

    memory_reset_peak_usage();

    $counter = 0;

    $generator = $service->run(
        callbacks: $clonedCallbacks,
        timeoutSeconds: 3
    );

    foreach ($generator as $result) {
        ++$counter;

        if ($result->error) {
            echo 'ERROR: ' . $result->taskKey . ': ' . $result->error->message . PHP_EOL;

            continue;
        }

        echo 'INFO: ' . $result->taskKey . ': ' . substr($result->result, 0, 50) . PHP_EOL;
    }

    $end = microtime(true);

    $executionTime = $end - $start;

    $metrics[$driverClass] = [
        'memory'         => memory_get_peak_usage(true) / 1024 / 1024,
        'execution_time' => $executionTime,
        'count'          => $counter,
    ];
}

echo '------------------------------------------' . PHP_EOL;

foreach ($metrics as $driverClass => $metric) {
    echo sprintf(
            "%s\tmemPeak:%f\ttime:%f\tcount:%d/%d",
            $driverClass,
            $metric['memory'],
            $metric['execution_time'],
            $metric['count'],
            $callbacksCount
        ) . PHP_EOL;
}

exit(0);

/**
 * @return array<mixed, Closure>
 */
function makeCaseUnique(int $count): array
{
    $result = [];

    while ($count--) {
        $result[__FUNCTION__ . '-' . $count] = static fn() => uniqid(more_entropy: true);
    }

    return $result;
}

/**
 * @return array<mixed, Closure>
 */
function makeCaseBigResponse(int $count): array
{
    $result = [];

    while ($count--) {
        $result[__FUNCTION__ . '-' . $count] = static fn() => str_repeat(
            uniqid(more_entropy: true),
            2000000
        );
    }

    return $result;
}

/**
 * @return array<mixed, Closure>
 */
function makeCaseMemoryLimit(int $count): array
{
    $result = [];

    while ($count--) {
        $result[__FUNCTION__ . '-' . $count] = static fn() => str_repeat(
            uniqid(more_entropy: true),
            3000000000
        );
    }

    return $result;
}

/**
 * @return array<mixed, Closure>
 */
function makeCaseSleep(int $count, int $sec): array
{
    $result = [];

    while ($count--) {
        $result[__FUNCTION__ . '-' . $count] = static function () use ($sec) {
            sleep($sec);

            return "sleep $sec";
        };
    }

    return $result;
}

/**
 * @return array<mixed, Closure>
 */
function makeCaseThrow(int $count): array
{
    $result = [];

    while ($count--) {
        $result[__FUNCTION__ . '-' . $count] = static fn() => throw new RuntimeException(
            "exception: $count " . uniqid(more_entropy: true)
        );
    }

    return $result;
}
