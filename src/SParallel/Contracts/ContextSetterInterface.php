<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use SParallel\Services\Context;

interface ContextSetterInterface
{
    public function set(Context $context): void;
}
