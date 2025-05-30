<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;

class InvalidMongodbBulkWriteOperationException extends RuntimeException
{
    public function __construct(string $operationType)
    {
        parent::__construct(
            message: "Invalid bulk write operation type [$operationType]",
        );
    }
}
