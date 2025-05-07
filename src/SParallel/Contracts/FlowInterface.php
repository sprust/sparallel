<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Generator;
use SParallel\Entities\SocketServer;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Objects\TaskResult;
use SParallel\Services\Context;

interface FlowInterface
{
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
