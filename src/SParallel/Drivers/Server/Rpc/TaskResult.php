<?php

declare(strict_types=1);

namespace SParallel\Drivers\Server\Rpc;

use Closure;

readonly class TaskResult
{
    public function __construct(
        public int|string $key,
        public Closure $callback,
    ) {
    }
}
