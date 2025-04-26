<?php

declare(strict_types=1);

namespace SParallel\Objects;

readonly class ProcessParentMessage
{
    public function __construct(
        public mixed $taskKey,
        public string $serializedContext,
        public string $serializedCanceler,
        public string $serializedCallback,
    ) {
    }
}
