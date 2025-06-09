<?php

declare(strict_types=1);

namespace SParallel\TestCases;

use Closure;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SParallel\Contracts\DriverFactoryInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\SParallelWorkers;

readonly class Benchmark
{
    public function __construct(
        private int $uniqueCount = 0,
        private int $bigResponseCount = 0,
        private int $sleepCount = 0,
        private int $sleepSec = 1,
        private int $memoryLimitCount = 0,
        private int $throwCount = 0,
    ) {
    }

    /**
     * @param array<class-string<DriverInterface>> $driverClasses
     *
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ContextCheckerException
     */
    public function start(
        ContainerInterface $container,
        array $driverClasses,
        int $timeoutSeconds,
        int $workersLimit
    ): void {
        $callbacks = [
            ...$this->makeCaseUnique(),
            ...$this->makeCaseBigResponse(),
            ...$this->makeCaseSleep(),
            ...$this->makeCaseMemoryLimit(),
            ...$this->makeCaseThrow(),
        ];

        $keys = array_keys($callbacks);

        shuffle($keys);

        $shuffled = [];

        foreach ($keys as $key) {
            $shuffled[$key] = $callbacks[$key];
        }

        $callbacks = $shuffled;

        $callbacksCount = count($callbacks);

        $metrics = [];

        foreach ($driverClasses as $driverClass) {
            echo '------------------------------------------' . PHP_EOL;
            echo 'Driver: ' . $driverClass . PHP_EOL;
            echo '------------------------------------------' . PHP_EOL;

            $start = microtime(true);

            $clonedCallbacks = array_merge($callbacks);

            $container->get(DriverFactoryInterface::class)->forceDriver(
                $container->get($driverClass),
            );

            /** @var SParallelWorkers $workers */
            $workers = $container->get(SParallelWorkers::class);

            memory_reset_peak_usage();

            $counter = 0;

            $generator = $workers->run(
                callbacks: $clonedCallbacks,
                timeoutSeconds: $timeoutSeconds,
                workersLimit: $workersLimit
            );

            foreach ($generator as $result) {
                ++$counter;

                if ($result->exception) {
                    echo sprintf(
                        "%f\t%s\tERROR\t%s\t%s\n",
                        microtime(true),
                        $result->taskKey,
                        $counter,
                        substr($result->exception->getMessage(), 0, 50),
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
    }

    /**
     * @return array<mixed, Closure>
     */
    private function makeCaseUnique(): array
    {
        $count = $this->uniqueCount;

        $result = [];

        while ($count--) {
            $result['success' . '-' . $count] = static fn() => $count . ': ' . uniqid(more_entropy: true);
        }

        return $result;
    }

    /**
     * @return array<mixed, Closure>
     */
    private function makeCaseBigResponse(): array
    {
        $count = $this->bigResponseCount;

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
    private function makeCaseMemoryLimit(): array
    {
        $count = $this->memoryLimitCount;

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
    private function makeCaseSleep(): array
    {
        $count = $this->sleepCount;
        $sec   = $this->sleepSec;

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
    private function makeCaseThrow(): array
    {
        $count = $this->throwCount;

        $result = [];

        while ($count--) {
            $result['except' . '-' . $count] = static fn() => throw new Exception(
                "$count: exception: " . uniqid(more_entropy: true)
            );
        }

        return $result;
    }

}
