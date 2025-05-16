<?php

declare(strict_types=1);

ini_set('memory_limit', '1G');

use SParallel\Contracts\DriverFactoryInterface;
use SParallel\Flows\ASync\Fork\ForkDriver;
use SParallel\Flows\ASync\Hybrid\HybridDriver;
use SParallel\Flows\ASync\Process\ProcessDriver;
use SParallel\Services\SParallelService;
use SParallel\TestsImplementation\TestContainer;
use SParallel\TestsImplementation\TestEventsRepository;
use SParallel\TestsImplementation\TestLogger;
use SParallel\TestsImplementation\TestProcessesRepository;
use SParallel\TestsImplementation\TestSocketFilesRepository;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @var array<mixed, Closure> $callbacks
 */
$callbacks = [
    //...makeCaseUnique(10),
    //...makeCaseBigResponse(5),
    //...makeCaseSleep(count: 5, sec: 1),
    ...makeCaseMemoryLimit(5),
    //...makeCaseThrow(5),
];

/** @var array<class-string<SParallel\Contracts\DriverInterface>> $driverClasses */
$driverClasses = [
    ProcessDriver::class,
    //ForkDriver::class,
    //HybridDriver::class,
];

$timeoutSeconds = 5;
$workersLimit   = 5;

$keys = array_keys($callbacks);

shuffle($keys);

$shuffled = [];

foreach ($keys as $key) {
    $shuffled[$key] = $callbacks[$key];
}

$callbacks = $shuffled;

$callbacksCount = count($callbacks);

$metrics = [];

$container = TestContainer::resolve();

$container->get(TestProcessesRepository::class)->flush();
$container->get(TestSocketFilesRepository::class)->flush();
$container->get(TestEventsRepository::class)->flush();

TestLogger::flush();

foreach ($driverClasses as $driverClass) {
    echo '------------------------------------------' . PHP_EOL;
    echo 'Driver: ' . $driverClass . PHP_EOL;
    echo '------------------------------------------' . PHP_EOL;

    $start = microtime(true);

    $clonedCallbacks = array_merge($callbacks);

    $container->get(DriverFactoryInterface::class)->forceDriver(
        $container->get($driverClass),
    );

    $service = $container->get(SParallelService::class);

    memory_reset_peak_usage();

    $counter = 0;

    $generator = $service->run(
        callbacks: $clonedCallbacks,
        timeoutSeconds: $timeoutSeconds,
        workersLimit: $workersLimit
    );

    foreach ($generator as $result) {
        ++$counter;

        if ($result->error) {
            echo sprintf(
                "%f\t%s\tERROR\t%s\t%s\n",
                microtime(true),
                $result->taskKey,
                $counter,
                $result->error->message,
            );

            continue;
        }

        echo sprintf(
            "%f\t%s\tINFO\t%s\t%s\n",
            microtime(true),
            $result->taskKey,
            $counter,
            substr($result->result, 0, 50),
        );
    }

    $end = microtime(true);

    $executionTime = $end - $start;

    $metrics[$driverClass] = [
        'memory'         => memory_get_peak_usage(true) / 1024 / 1024,
        'execution_time' => $executionTime,
        'count'          => $counter,
    ];
}
//}

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
        $result['success' . '-' . $count] = static fn() => $count . ': ' . uniqid(more_entropy: true);
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
        $result['big-resp' . '-' . $count] = static fn() => $count . ': ' . str_repeat(
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
        $result['mem-lim' . '-' . $count] = static fn() => $count . ': ' . str_repeat(
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
        $result['sleeping' . '-' . $count] = static function () use ($count, $sec) {
            sleep($sec);

            return "$count: sleep $sec";
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
        $result['except' . '-' . $count] = static fn() => throw new RuntimeException(
            "$count: exception: " . uniqid(more_entropy: true)
        );
    }

    return $result;
}
