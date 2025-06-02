<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Generator;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Objects\TaskResult;

interface WaitGroupInterface
{
    /**
     * @return Generator<int, TaskResult>
     *
     * @throws ContextCheckerException
     */
    public function get(): Generator;

    public function cancel(): void;
}
