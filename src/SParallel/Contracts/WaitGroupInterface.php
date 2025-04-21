<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Generator;
use SParallel\Exceptions\SParallelTimeoutException;
use SParallel\Objects\TaskResult;

interface WaitGroupInterface
{
    /**
     * @return Generator<TaskResult>
     * @throws SParallelTimeoutException
     */
    public function get(): Generator;

    public function break(): void;
}
