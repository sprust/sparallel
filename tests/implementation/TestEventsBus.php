<?php

declare(strict_types=1);

namespace SParallel\TestsImplementation;

use SParallel\Contracts\EventsBusInterface;
use SParallel\Entities\Context;
use SParallel\Exceptions\RpcCallException;
use Throwable;

class TestEventsBus implements EventsBusInterface
{
    public function __construct(
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

    public function onServerGone(Context $context, RpcCallException $exception): void
    {
        $this->eventsRepository->add(__FUNCTION__);
    }
}
