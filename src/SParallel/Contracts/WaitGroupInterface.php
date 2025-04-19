<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Generator;
use SParallel\Exceptions\SParallelTimeoutException;
use SParallel\Objects\ResultObject;

interface WaitGroupInterface
{
    /**
     * @return Generator<ResultObject>
     * @throws SParallelTimeoutException
     */
    public function get(): Generator;

    public function break(): void;
}
