<?php

declare(strict_types=1);

namespace SParallel\Objects;

readonly class ProcessChildMessage
{
    public function __construct(
        public mixed $taskKey,
        public string $operation,
        public string $payload,
    ) {
    }
}
