<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use SParallel\Objects\Context;
use Throwable;

interface EventsBusInterface
{
    public function flowStarting(): void;

    public function flowFailed(Throwable $exception): void;

    public function flowFinished(): void;

    public function taskStarting(string $driverName, ?Context $context): void;

    public function taskFailed(string $driverName, ?Context $context, Throwable $exception): void;

    public function taskFinished(string $driverName, ?Context $context): void;

    public function unixSocketCreated(string $socketPath): void;

    public function unixSocketClosed(string $socketPath): void;

    public function processCreated(int $pid): void;

    public function processFinished(int $pid): void;
}
