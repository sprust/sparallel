<?php

declare(strict_types=1);

namespace SParallel\Contracts;

interface ProcessCommandResolverInterface
{
    public function get(): string;
}
