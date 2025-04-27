<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use SParallel\Services\Context;

interface ContextResolverInterface
{
    public function set(Context $context): void;
    public function get(): Context;
}
