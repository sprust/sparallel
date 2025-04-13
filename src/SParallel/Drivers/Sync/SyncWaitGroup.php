<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use Closure;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Objects\ResultObject;
use SParallel\Objects\ResultsObject;
use Throwable;

class SyncWaitGroup implements WaitGroupInterface
{
    /**
     * @param array<mixed, Closure> $callbacks
     */
    public function __construct(
        protected array $callbacks,
    ) {
    }

    public function current(): ResultsObject
    {
        $results = new ResultsObject();

        foreach ($this->callbacks as $key => $callback) {
            try {
                $result = new ResultObject(
                    result: $callback()
                );
            } catch (Throwable $exception) {
                $result = new ResultObject(
                    exception: $exception
                );
            }

            $results->addResult(
                key: $key,
                result: $result
            );
        }

        return $results;
    }

    public function break(): void
    {
        // no-op
    }
}
