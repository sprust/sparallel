<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;

class CouldNotForkProcessException extends RuntimeException
{
    public function __construct(public readonly mixed $taskKey)
    {
        parent::__construct(
            "Could not fork process for task [$taskKey]."
        );
    }
}
