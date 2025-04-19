<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use Generator;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\SParallelTimeoutException;
use SParallel\Objects\ResultObject;

interface DriverInterface
{
    /**
     * @param array<mixed, Closure> $callbacks
     *
     * @return Generator<ResultObject>|Generator<false>
     *
     * @throws SParallelTimeoutException
     */
    public function run(array &$callbacks, Timer $timer): Generator;
}
