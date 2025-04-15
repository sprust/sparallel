<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use SParallel\Objects\Context;
use Throwable;

interface TaskEventsBusInterface
{
    public function starting(string $driverName, ?Context $context): void;

    public function failed(string $driverName, ?Context $context, Throwable $exception): void;

    public function finished(string $driverName, ?Context $context): void;
}
