<?php

declare(strict_types=1);

namespace SParallel\Objects;

use Throwable;

readonly class ResultObject
{
    public ?ResultErrorObject $error;
    public mixed $result;

    public function __construct(
        public mixed $key,
        ?Throwable $exception = null,
        mixed $result = null
    ) {
        $this->error  = $exception ? new ResultErrorObject($exception) : null;
        $this->result = $result;
    }
}
