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
        protected ?Closure $beforeTask = null,
        protected ?Closure $afterTask = null,
        protected ?Closure $failedTask = null,
    ) {
    }

    public function current(): ResultsObject
    {
        $results = new ResultsObject();

        foreach ($this->callbacks as $key => $callback) {
            if (!is_null($this->beforeTask)) {
                call_user_func($this->beforeTask);
            }

            try {
                $result = new ResultObject(
                    result: $callback()
                );
            } catch (Throwable $exception) {
                if (!is_null($this->failedTask)) {
                    call_user_func($this->failedTask, $exception);
                }

                $result = new ResultObject(
                    exception: $exception
                );
            } finally {
                if (!is_null($this->afterTask)) {
                    call_user_func($this->afterTask);
                }
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
