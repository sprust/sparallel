<?php

declare(strict_types=1);

namespace SParallel\Objects;

use Throwable;

readonly class TaskResult
{
    public function __construct(
        public int|string $taskKey,
        public ?Throwable $exception = null,
        public mixed $result = null
    ) {
    }
}
