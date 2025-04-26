<?php

declare(strict_types=1);

namespace SParallel\Contracts;

interface HybridScriptPathResolverInterface
{
    public function get(): string;
}
