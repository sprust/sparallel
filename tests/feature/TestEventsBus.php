<?php

namespace SParallel\Tests;

use SParallel\Contracts\EventsBusInterface;
use SParallel\Services\Context;
use Throwable;

class TestEventsBus implements EventsBusInterface
{
    public function __construct(
        protected TestProcessesRepository $processesRepository,
    ) {
    }

    public function flowStarting(Context $context): void
    {
        TestCounter::increment();
    }

    public function flowFailed(Context $context, Throwable $exception): void
    {
        TestCounter::increment();
    }

    public function flowFinished(Context $context): void
    {
        TestCounter::increment();
    }

    public function taskStarting(string $driverName, Context $context): void
    {
        TestCounter::increment();
    }

    public function taskFailed(string $driverName, Context $context, Throwable $exception): void
    {
        TestCounter::increment();
    }

    public function taskFinished(string $driverName, Context $context): void
    {
        TestCounter::increment();
    }

    public function processCreated(int $pid): void
    {
        $this->processesRepository->add($pid);
    }

    public function processFinished(int $pid): void
    {
        $this->processesRepository->deleteByPid($pid);
    }
}
