<?php

declare(strict_types=1);

namespace SParallel\Objects;

readonly class ThreadResult
{
    public function __construct(
        public int|string $key,
        public mixed $result,
    ) {
    }
}
