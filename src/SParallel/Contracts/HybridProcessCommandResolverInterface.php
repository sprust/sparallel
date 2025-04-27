<?php

declare(strict_types=1);

namespace SParallel\Contracts;

interface HybridProcessCommandResolverInterface
{
    public function get(): string;
}
