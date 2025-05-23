<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;

class UnexpectedServerTaskException extends RuntimeException
{
    public function __construct(
        public readonly string $unexpectedTaskUuid,
    ) {
        parent::__construct(
            "Server task key [$this->unexpectedTaskUuid] not found in processTask list",
        );
    }
}
