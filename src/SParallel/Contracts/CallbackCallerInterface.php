<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use SParallel\Services\Context;

interface CallbackCallerInterface
{
    public function call(Context $context, Closure $callback): mixed;
}
