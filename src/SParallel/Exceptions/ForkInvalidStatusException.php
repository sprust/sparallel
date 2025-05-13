<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;
use SParallel\Flows\ASync\Fork\ForkService;

/**
 * TODO: delete maybe. used in
 *
 * @see ForkService::isFinished()
 */
class ForkInvalidStatusException extends RuntimeException
{
    public function __construct(public readonly int $pid)
    {
        parent::__construct(
            "Could not reliably manage task that uses process id [$pid]"
        );
    }
}
