<?php

declare(strict_types=1);

namespace SParallel\Services;

use Closure;
use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Exceptions\ParallelTimeoutException;
use SParallel\Objects\ResultsObject;

class ParallelService
{
    public function __construct(
        protected DriverInterface $driver,
    ) {
    }

    /**
     * @param array<Closure> $callbacks
     *
     * @throws ParallelTimeoutException
     */
    public function wait(
        array $callbacks,
        int $waitMicroseconds = 0,
        bool $breakAtFirstError = false
    ): ResultsObject {
        $waitGroup = $this->driver->wait(
            callbacks: $callbacks
        );

        $expectedResultCount = count($callbacks);

        $startTime       = microtime(true);
        $comparativeTime = $waitMicroseconds / 1_000_000;

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
        }

        return $results;
    }

    /**
     * @throws ParallelTimeoutException
     */
    private function checkTimedOut(WaitGroupInterface $waitGroup, float $startTime, float $comparativeTime): void
    {
        if ($comparativeTime > 0 && (microtime(true) - $startTime) > $comparativeTime) {
            $waitGroup->break();

            throw new ParallelTimeoutException();
        }
    }
}
