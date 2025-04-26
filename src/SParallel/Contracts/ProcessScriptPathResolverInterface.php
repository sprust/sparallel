<?php

declare(strict_types=1);

namespace SParallel\Contracts;

interface ProcessScriptPathResolverInterface
{
    public function get(): string;
}
