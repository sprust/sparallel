<?php

declare(strict_types=1);

namespace SParallel\Objects;

use Throwable;

readonly class ThreadResult
{
    public function __construct(
        public int|string $key,
        public mixed $result = null,
        public ?Throwable $exception = null,
    ) {
    }
}
