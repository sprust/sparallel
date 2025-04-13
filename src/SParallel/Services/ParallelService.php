<?php

namespace SParallel\Services;

use Closure;
use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Objects\ResultsObject;

class ParallelService
{
    public function __construct(
        protected DriverInterface $driver,
    ) {
    }

    /**
     * @param array<Closure> $callbacks
     */
    public function run(array $callbacks): ResultsObject
    {
        $results = $this->driver->run($callbacks);

        $expectedResultCount = count($callbacks);

        $resultsCount = $results->count();

        if ($resultsCount === $expectedResultCount) {
            $results->finish();
        } elseif ($resultsCount >= $expectedResultCount) {
            throw new RuntimeException(
                "Expected result count of $expectedResultCount, but got " . $resultsCount
            );
        }

        return $results;
    }
}
