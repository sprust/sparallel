<?php

declare(strict_types=1);

namespace SParallel\Services;

use Closure;
use Generator;
use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\SParallelTimeoutException;
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
     * @throws SParallelTimeoutException
     */
    public function wait(
        array &$callbacks,
        int $timeoutSeconds,
        bool $breakAtFirstError = false
    ): TaskResults {
        $results = new TaskResults();

        $generator = $this->run(
            callbacks: $callbacks,
            timeoutSeconds: $timeoutSeconds,
            breakAtFirstError: $breakAtFirstError
        );

        foreach ($generator as $result) {
            /** @var TaskResult $result */

            $results->addResult(
                result: $result
            );
        }

        if ($results->count() === count($callbacks)) {
            $results->finish();
        }

        return $results;
    }

    /**
     * @param array<mixed, Closure> $callbacks
     *
     * @return Generator<TaskResult>
     *
     * @throws SParallelTimeoutException
     */
    public function run(
        array &$callbacks,
        int $timeoutSeconds,
        bool $breakAtFirstError = false
    ): Generator {
        $this->eventsBus->flowStarting();

        try {
            return $this->onRun(
                callbacks: $callbacks,
                timeoutSeconds: $timeoutSeconds,
                breakAtFirstError: $breakAtFirstError
            );
        } catch (SParallelTimeoutException $exception) {
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

    /**
     * @param array<mixed, Closure> $callbacks
     *
     * @return Generator<TaskResult>
     *
     * @throws SParallelTimeoutException
     */
    private function onRun(
        array &$callbacks,
        int $timeoutSeconds = 0,
        bool $breakAtFirstError = false
    ): Generator {
        $timer = new Timer(
            timeoutSeconds: $timeoutSeconds
        );

        $waitGroup = $this->driver->run(
            callbacks: $callbacks,
            timer: $timer
        );

        $brokeResult = null;

        foreach ($waitGroup->get() as $result) {
            $timer->check();

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
    }
}
