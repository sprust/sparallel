<?php

declare(strict_types=1);

namespace SParallel\Objects;

use Throwable;

readonly class ResultErrorObject
{
    public string $exceptionClass;
    public string $message;
    public string $traceAsString;
    public ?ResultErrorObject $previous;

    public function __construct(Throwable $exception)
    {
        $previous = $exception->getPrevious();

        $this->exceptionClass = $exception::class;
        $this->message        = $exception->getMessage();
        $this->traceAsString  = $exception->getTraceAsString();
        $this->previous       = $previous ? new ResultErrorObject($previous) : null;
    }
}
