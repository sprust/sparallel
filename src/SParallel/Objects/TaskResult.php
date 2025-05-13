<?php

declare(strict_types=1);

namespace SParallel\Objects;

use Throwable;

readonly class TaskResult
{
    public ?TaskResultError $error;
    public mixed $result;

    public function __construct(
        public int|string $taskKey,
        ?Throwable $exception = null,
        mixed $result = null
    ) {
        $this->error  = $exception ? new TaskResultError($exception) : null;
        $this->result = $result;
    }
}
