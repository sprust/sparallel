<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use Exception;
use SParallel\Contracts\CancelerInterface;
use Throwable;

class CancelerException extends Exception
{
    public function __construct(
        public readonly CancelerInterface $canceler,
        public readonly Throwable $exception
    ) {
        parent::__construct(
            sprintf(
                'Cancelled by [%s] with exception [%s: %s]',
                $canceler::class,
                $exception::class,
                $exception->getMessage()
            ),
            previous: $this->exception
        );
    }
}
