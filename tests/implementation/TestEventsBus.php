<?php

declare(strict_types=1);

namespace SParallel\TestsImplementation;

use SParallel\Contracts\EventsBusInterface;
use SParallel\Services\Context;
use Throwable;

class TestEventsBus implements EventsBusInterface
{
    public function __construct(
        protected TestProcessesRepository $processesRepository,
        protected TestEventsRepository $eventsRepository,
    ) {
    }

    public function flowStarting(Context $context): void
    {
        $this->eventsRepository->add(__FUNCTION__);
    }

    public function flowFailed(Context $context, Throwable $exception): void
    {
        $this->eventsRepository->add(__FUNCTION__);
    }

    public function flowFinished(Context $context): void
    {
        $this->eventsRepository->add(__FUNCTION__);
    }

    public function taskStarting(string $driverName, Context $context): void
    {
        $this->eventsRepository->add(__FUNCTION__);
    }

    public function taskFailed(string $driverName, Context $context, Throwable $exception): void
    {
        $this->eventsRepository->add(__FUNCTION__);
    }

    public function taskFinished(string $driverName, Context $context): void
    {
        $this->eventsRepository->add(__FUNCTION__);
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
