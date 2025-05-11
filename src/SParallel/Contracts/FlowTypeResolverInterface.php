<?php

declare(strict_types=1);

namespace SParallel\Contracts;

interface FlowTypeResolverInterface
{
    public function isAsync(): bool;
}
