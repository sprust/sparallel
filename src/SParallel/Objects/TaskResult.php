<?php

declare(strict_types=1);

namespace SParallel\Objects;

use Throwable;

readonly class TaskResult
{
    public ?TaskResultError $error;
    public mixed $result;

    public function __construct(
        public mixed $key,
        ?Throwable $exception = null,
        mixed $result = null
    ) {
        $this->error  = $exception ? new TaskResultError($exception) : null;
        $this->result = $result;
    }
}
