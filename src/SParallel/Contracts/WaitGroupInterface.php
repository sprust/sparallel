<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Generator;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Objects\TaskResult;

interface WaitGroupInterface
{
    /**
     * @return Generator<TaskResult>
     *
     * @throws ContextCheckerException
     */
    public function get(): Generator;

    public function break(): void;
}
