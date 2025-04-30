<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Throwable;

interface ContextCheckerInterface
{
    /**
     * @throws Throwable
     */
    public function check(): void;
}
