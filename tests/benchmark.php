<?php

declare(strict_types=1);

ini_set('memory_limit', '1G');

use SParallel\Contracts\TaskManagerFactoryInterface;
use SParallel\Flows\ASync\Fork\ForkTaskManager;
use SParallel\Flows\ASync\Hybrid\HybridTaskManager;
use SParallel\Flows\ASync\Process\ProcessTaskManager;
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

/** @var array<class-string<\SParallel\Contracts\TaskManagerInterface>> $taskManagerClasses */
$taskManagerClasses = [
    ProcessTaskManager::class,
    ForkTaskManager::class,
    HybridTaskManager::class,
];

$metrics = [];

$container = TestContainer::resolve();

foreach ($taskManagerClasses as $taskManagerClass) {
    echo '------------------------------------------' . PHP_EOL;
    echo 'Driver: ' . $taskManagerClass . PHP_EOL;
    echo '------------------------------------------' . PHP_EOL;

    $start = microtime(true);

    $clonedCallbacks = array_merge($callbacks);

    $container->get(TaskManagerFactoryInterface::class)->forceDriver(
        $container->get($taskManagerClass),
    );

    $service = $container->get(SParallelService::class);

    memory_reset_peak_usage();

    $counter = 0;

    $generator = $service->run(
        callbacks: $clonedCallbacks,
        timeoutSeconds: 5,
    //workersLimit: 5
    );

    foreach ($generator as $result) {
        ++$counter;

        if ($result->error) {
            echo sprintf(
                "%f\t%s\tERROR\t%s\n",
                microtime(true),
                $result->taskKey,
                $result->error->message
            );

            continue;
        }

        echo sprintf(
            "%f\t%s\tINFO\t%s\n",
            microtime(true),
            $result->taskKey,
            substr($result->result, 0, 50)
        );
    }

    $end = microtime(true);

    $executionTime = $end - $start;

    $metrics[$taskManagerClass] = [
        'memory'         => memory_get_peak_usage(true) / 1024 / 1024,
        'execution_time' => $executionTime,
        'count'          => $counter,
    ];
}

echo '------------------------------------------' . PHP_EOL;

foreach ($metrics as $taskManagerClass => $metric) {
    echo sprintf(
            "%s\tmemPeak:%f\ttime:%f\tcount:%d/%d",
            $taskManagerClass,
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
