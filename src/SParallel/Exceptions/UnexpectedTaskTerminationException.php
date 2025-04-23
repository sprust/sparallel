<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;

class UnexpectedTaskTerminationException extends RuntimeException
{
    public function __construct(
        public readonly mixed $taskKey,
        public readonly ?string $description = null,
    ) {
        parent::__construct(
            "Unexpected process termination of task [$taskKey]"
            . ($this->description ? (": " . trim($this->description)) : ".")
        );
    }
}
