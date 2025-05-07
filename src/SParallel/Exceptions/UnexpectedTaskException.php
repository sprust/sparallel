<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;

class UnexpectedTaskException extends RuntimeException
{
    public function __construct(
        public readonly int|string $unexpectedTaskKey,
    ) {
        parent::__construct(
            "Task key [$this->unexpectedTaskKey] not found in processTask list",
        );
    }
}
