<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb\Operations;

readonly class RunningOperation
{
    public function __construct(
        public string $error,
        public string $uuid,
    ) {
    }
}
