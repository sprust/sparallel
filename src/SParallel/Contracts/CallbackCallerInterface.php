<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use SParallel\Services\Canceler;

interface CallbackCallerInterface
{
    public function call(Closure $callback, Canceler $canceler): mixed;
}
