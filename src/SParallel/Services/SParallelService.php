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
    public function wait(
        array &$callbacks,
        int $timeoutSeconds,
        bool $breakAtFirstError = false,
        ?Canceler $canceler = null
    ): TaskResults {
        $tasksCount = count($callbacks);

        $results = new TaskResults();

        $generator = $this->run(
            callbacks: $callbacks,
            timeoutSeconds: $timeoutSeconds,
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
        bool $breakAtFirstError = false,
        ?Canceler $canceler = null
    ): Generator {
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
                canceler: $canceler
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
        }

        $this->eventsBus->flowFinished();
    }
}
