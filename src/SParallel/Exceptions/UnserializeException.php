<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;

class UnserializeException extends RuntimeException
{
    public function __construct(
        public readonly string $expected,
        public readonly mixed $got,
    ) {
        $actualType = gettype($this->got);

        parent::__construct(
            "Failed to unserialize. Expected [$this->expected], got [$actualType]"
        );
    }
}
