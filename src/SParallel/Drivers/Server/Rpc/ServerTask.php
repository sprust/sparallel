<?php

declare(strict_types=1);

namespace SParallel\Drivers\Server\Rpc;

use Closure;
use SParallel\Services\Context;

readonly class ServerTask
{
    public function __construct(
        public Context $context,
        public int|string $key,
        public Closure $callback,
    ) {
    }
}
