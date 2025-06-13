<?php

declare(strict_types=1);

namespace SParallel\Server\Dto;

use Closure;
use SParallel\Entities\Context;

readonly class ServerTask
{
    public function __construct(
        public Context $context,
        public int|string $key,
        public Closure $callback,
    ) {
    }
}
