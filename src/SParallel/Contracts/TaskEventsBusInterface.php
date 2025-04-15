<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use SParallel\Objects\Context;
use Throwable;

interface TaskEventsBusInterface
{
    public function starting(?Context $context): void;

    public function failed(?Context $context, Throwable $exception): void;

    public function finished(?Context $context): void;
}
