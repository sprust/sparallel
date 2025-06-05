<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use SParallel\Entities\Context;
use SParallel\Exceptions\RpcCallException;
use Throwable;

interface EventsBusInterface
{
    public function flowStarting(Context $context): void;

    public function flowFailed(Context $context, Throwable $exception): void;

    public function flowFinished(Context $context): void;

    public function taskStarting(string $driverName, Context $context): void;

    public function taskFailed(string $driverName, Context $context, Throwable $exception): void;

    public function taskFinished(string $driverName, Context $context): void;

    public function onServerGone(Context $context, RpcCallException $exception): void;
}
