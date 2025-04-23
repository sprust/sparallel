<?php

declare(strict_types=1);

namespace SParallel\Objects;

use Throwable;

readonly class TaskResultError
{
    public string $exceptionClass;
    public string $message;
    public string $traceAsString;
    public ?TaskResultError $previous;

    public function __construct(Throwable $exception)
    {
        $previous = $exception->getPrevious();

        $this->exceptionClass = $exception::class;
        $this->message        = $exception->getMessage();
        $this->traceAsString  = $exception->getTraceAsString();
        $this->previous       = $previous ? new TaskResultError($previous) : null;
    }
}
