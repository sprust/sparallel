<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use Generator;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Objects\TaskResult;
use SParallel\Services\Context;

interface FlowInterface
{
    /**
     * @param array<int|string, Closure> $callbacks
     */
    public function start(
        Context $context,
        DriverInterface $driver,
        array &$callbacks,
        int $workersLimit,
    ): static;

    /**
     * @return Generator<TaskResult>
     *
     * @throws ContextCheckerException
     */
    public function get(): Generator;

    public function break(): void;
}
