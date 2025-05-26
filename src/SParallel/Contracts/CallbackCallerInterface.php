<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use SParallel\Entities\Context;

interface CallbackCallerInterface
{
    public function call(Context $context, Closure $callback): mixed;
}
