<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;
use Generator;
use SParallel\Entities\SocketServer;
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
        TaskManagerInterface $taskManager,
        array &$callbacks,
        int $workersLimit,
        SocketServer $socketServer
    ): static;

    /**
     * @return Generator<TaskResult>
     *
     * @throws ContextCheckerException
     */
    public function get(): Generator;

    public function break(): void;
}
