<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;

class UnexpectedTaskOperationException extends RuntimeException
{
    public function __construct(
        public readonly int|string $taskKey,
        public readonly string $operation,
    ) {
        parent::__construct(
            sprintf(
                'Unknown operation [%s] for task key [%s]',
                $this->operation,
                $this->taskKey
            )
        );
    }
}
