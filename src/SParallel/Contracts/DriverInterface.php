<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use SParallel\Objects\ResultsObject;

interface DriverInterface
{
    /**
     * @param array<Closure> $callbacks
     */
    public function run(array $callbacks): ResultsObject;
}
