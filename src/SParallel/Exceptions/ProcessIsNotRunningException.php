<?php

namespace SParallel\Exceptions;

use RuntimeException;

class ProcessIsNotRunningException extends RuntimeException
{
    public function __construct(
        public readonly ?int $pid,
        public readonly ?string $description = null,
    ) {
        parent::__construct(
            sprintf(
                'Process[%s] is not running: %s',
                $this->pid,
                $this->description ?: 'No description provided'
            )
        );
    }
}
