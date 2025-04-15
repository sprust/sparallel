<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use Closure;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\WaitGroupInterface;
use Throwable;

class SyncDriver implements DriverInterface
{
    /**
     * @param Closure(Throwable $exception): void|null $failedTask
     */
    public function __construct(
        protected ?Closure $beforeTask = null,
        protected ?Closure $afterTask = null,
        protected ?Closure $failedTask = null,
    ) {
    }

    public function wait(array $callbacks): WaitGroupInterface
    {
        return new SyncWaitGroup(
            callbacks: $callbacks,
            beforeTask: $this->beforeTask,
            afterTask: $this->afterTask,
            failedTask: $this->failedTask,
        );
    }
}
