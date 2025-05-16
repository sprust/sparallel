<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Objects\TaskResult;
use SParallel\Services\Context;

interface DriverInterface
{
    /**
     * @param array<int|string, Closure> $callbacks
     */
    public function init(
        Context $context,
        array $callbacks,
        int $workersLimit,
    ): void;

    public function createTask(
        Context $context,
        int|string $taskKey,
        Closure $callback
    ): TaskInterface;

    /**
     * @throws ContextCheckerException
     */
    public function getResult(Context $context): TaskResult|false;

    public function break(Context $context): void;
}
