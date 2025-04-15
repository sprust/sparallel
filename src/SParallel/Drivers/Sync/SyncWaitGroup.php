<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use Closure;
use SParallel\Contracts\TaskEventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Objects\Context;
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
        protected ?Context $context = null,
        protected ?TaskEventsBusInterface $taskEventsBus = null,
    ) {
    }

    public function current(): ResultsObject
    {
        $results = new ResultsObject();

        foreach ($this->callbacks as $key => $callback) {
            $this->taskEventsBus?->starting(
                driverName: SyncDriver::DRIVER_NAME,
                context: $this->context
            );

            try {
                $result = new ResultObject(
                    result: $callback()
                );
            } catch (Throwable $exception) {
                $this->taskEventsBus?->failed(
                    driverName: SyncDriver::DRIVER_NAME,
                    context: $this->context,
                    exception: $exception
                );

                $result = new ResultObject(
                    exception: $exception
                );
            } finally {
                $this->taskEventsBus?->finished(
                    driverName: SyncDriver::DRIVER_NAME,
                    context: $this->context
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
