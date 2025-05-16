<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;

class CantCreateProcessException extends RuntimeException
{
    public function __construct(string $command)
    {
        parent::__construct(
            "Could not create process for command [$command]."
        );
    }
}
