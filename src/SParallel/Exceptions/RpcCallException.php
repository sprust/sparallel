<?php

declare(strict_types=1);

namespace SParallel\Exceptions;

use RuntimeException;
use Throwable;

class RpcCallException extends RuntimeException
{
    public function __construct(Throwable $exception)
    {
        parent::__construct(
            $exception->getMessage() . PHP_EOL . 'Trace:' . PHP_EOL . $exception->getTraceAsString(),
        );
    }
}
