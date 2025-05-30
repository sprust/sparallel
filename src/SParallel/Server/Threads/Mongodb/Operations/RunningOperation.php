<?php

declare(strict_types=1);

namespace SParallel\Server\Threads\Mongodb\Operations;

readonly class RunningOperation
{
    public function __construct(
        public string $error,
        public string $uuid,
    ) {
    }
}
