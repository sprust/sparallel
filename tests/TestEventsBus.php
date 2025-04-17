<?php

namespace SParallel\Tests;

use SParallel\Contracts\EventsBusInterface;
use SParallel\Objects\Context;
use Throwable;

class TestEventsBus implements EventsBusInterface
{
    public function flowStarting(): void
    {
        TestCounter::increment();
    }

    public function flowFailed(Throwable $exception): void
    {
        TestCounter::increment();
    }

    public function flowFinished(): void
    {
        TestCounter::increment();
    }

    public function taskStarting(string $driverName, ?Context $context): void
    {
        TestCounter::increment();
    }

    public function taskFailed(string $driverName, ?Context $context, Throwable $exception): void
    {
        TestCounter::increment();
    }

    public function taskFinished(string $driverName, ?Context $context): void
    {
        TestCounter::increment();
    }
}
