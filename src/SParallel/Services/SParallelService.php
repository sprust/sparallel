<?php

declare(strict_types=1);

namespace SParallel\Services;

use Closure;
use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Exceptions\SParallelTimeoutException;
use SParallel\Objects\ResultsObject;
use Throwable;

class SParallelService
{
    public function __construct(
        protected DriverInterface $driver,
        protected ?EventsBusInterface $eventsBus = null,
    ) {
    }

    /**
     * @param array<mixed, Closure> $callbacks
     *
     * @throws SParallelTimeoutException
     */
    public function wait(
        array $callbacks,
        int $waitMicroseconds = 0,
        bool $breakAtFirstError = false
    ): ResultsObject {
        $this->eventsBus?->flowStarting();

        try {
            return $this->onWait(
                callbacks: $callbacks,
                waitMicroseconds: $waitMicroseconds,
                breakAtFirstError: $breakAtFirstError
            );
        } catch (SParallelTimeoutException $exception) {
            $this->eventsBus?->flowFailed($exception);

            throw $exception;
        } catch (Throwable $exception) {
            $this->eventsBus?->flowFailed($exception);

            throw new RuntimeException(
                message: $exception->getMessage(),
                previous: $exception
            );
        } finally {
            $this->eventsBus?->flowFinished();
        }
    }

    /**
     * @param array<mixed, Closure> $callbacks
     *
     * @throws SParallelTimeoutException
     */
    private function onWait(
        array $callbacks,
        int $waitMicroseconds = 0,
        bool $breakAtFirstError = false
    ): ResultsObject {
        $waitGroup = $this->driver->wait(
            callbacks: $callbacks
        );

        $expectedResultCount = count($callbacks);

        $startTime       = (float) microtime(true);
        $comparativeTime = (float) ($waitMicroseconds / 1_000_000);

        while (true) {
            $results = $waitGroup->current();

            if ($breakAtFirstError && $results->hasFailed()) {
                $waitGroup->break();

                break;
            }

            $this->checkTimedOut(
                waitGroup: $waitGroup,
                startTime: $startTime,
                comparativeTime: $comparativeTime
            );

            $resultsCount = $results->count();

            if ($resultsCount > $expectedResultCount) {
                throw new RuntimeException(
                    "Expected result count of $expectedResultCount, but got " . $resultsCount
                );
            }

            if ($resultsCount === $expectedResultCount) {
                $results->finish();

                break;
            }

            usleep(100);
        }

        return $results;
    }

    /**
     * @throws SParallelTimeoutException
     */
    private function checkTimedOut(WaitGroupInterface $waitGroup, float $startTime, float $comparativeTime): void
    {
        if ($comparativeTime > 0 && (microtime(true) - $startTime) > $comparativeTime) {
            $waitGroup->break();

            throw new SParallelTimeoutException();
        }
    }
}
