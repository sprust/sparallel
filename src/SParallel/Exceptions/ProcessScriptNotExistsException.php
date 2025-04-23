<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;

class ProcessScriptNotExistsException extends RuntimeException
{
    public function __construct(public readonly string $scriptPath)
    {
        parent::__construct(
            "Process script [$this->scriptPath] not exists",
        );
    }
}
