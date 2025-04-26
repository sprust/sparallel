<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Generator;
use SParallel\Exceptions\CancelerException;
use SParallel\Objects\TaskResult;

interface WaitGroupInterface
{
    /**
     * @return Generator<TaskResult>
     *
     * @throws CancelerException
     */
    public function get(): Generator;

    public function break(): void;
}
