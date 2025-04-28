<?php

declare(strict_types=1);

namespace SParallel\Services;

use Closure;
use Generator;
use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\CancelerException;
use SParallel\Objects\TaskResult;
use SParallel\Objects\TaskResults;
use Throwable;

class SParallelService
{
    public function __construct(
        protected DriverInterface $driver,
        protected EventsBusInterface $eventsBus,
    ) {
    }

    /**
     * @param array<mixed, Closure> $callbacks
     *
     * @throws CancelerException
     */
    public function waitFirst(
        array &$callbacks,
        int $timeoutSeconds,
        bool $onlySuccess,
        int $workersLimit = 0,
        ?Canceler $canceler = null,
    ): ?TaskResult {
        $generator = $this->run(
            callbacks: $callbacks,
            timeoutSeconds: $timeoutSeconds,
            workersLimit: $workersLimit,
            canceler: $canceler
        );

        $result = null;

        foreach ($generator as $result) {
            /** @var TaskResult $result */

            if ($result->error && $onlySuccess) {
                continue;
            }

            break;
        }

        return $result;
    }

    /**
     * @param array<mixed, Closure> $callbacks
     *
     * @throws CancelerException
     */
    public function wait(
        array &$callbacks,
        int $timeoutSeconds,
        int $workersLimit = 0,
        bool $breakAtFirstError = false,
        ?Canceler $canceler = null,
    ): TaskResults {
        $tasksCount = count($callbacks);

        $results = new TaskResults();

        $generator = $this->run(
            callbacks: $callbacks,
            timeoutSeconds: $timeoutSeconds,
            workersLimit: $workersLimit,
            breakAtFirstError: $breakAtFirstError,
            canceler: $canceler
        );

        foreach ($generator as $result) {
            /** @var TaskResult $result */

            $results->addResult(
                result: $result
            );
        }

        if ($results->count() === $tasksCount) {
            $results->finish();
        }

        return $results;
    }

    /**
     * @param array<mixed, Closure> $callbacks
     *
     * @return Generator<TaskResult>
     *
     * @throws CancelerException
     */
    public function run(
        array &$callbacks,
        int $timeoutSeconds,
        int $workersLimit = 0,
        bool $breakAtFirstError = false,
        ?Canceler $canceler = null
    ): Generator {
        if ($workersLimit < 1) {
            $workersLimit = SOMAXCONN;
        } else {
            $workersLimit = min($workersLimit, SOMAXCONN);
        }

        $this->eventsBus->flowStarting();

        try {
            if (is_null($canceler)) {
                $canceler = new Canceler();
            }

            $canceler->add(
                new Timer(
                    timeoutSeconds: $timeoutSeconds
                )
            );

            $waitGroup = $this->driver->run(
                callbacks: $callbacks,
                canceler: $canceler,
                workersLimit: $workersLimit
            );

            $brokeResult = null;

            foreach ($waitGroup->get() as $result) {
                $canceler->check();

                if ($breakAtFirstError && $result->error) {
                    $waitGroup->break();

                    $brokeResult = $result;

                    break;
                }

                yield $result;
            }

            if ($brokeResult) {
                yield $brokeResult;
            }
        } catch (CancelerException $exception) {
            $this->eventsBus->flowFailed($exception);

            throw $exception;
        } catch (Throwable $exception) {
            $this->eventsBus->flowFailed($exception);

            throw new RuntimeException(
                message: $exception->getMessage(),
                previous: $exception
            );
        } finally {
            $this->eventsBus->flowFinished();
        }
    }
}
