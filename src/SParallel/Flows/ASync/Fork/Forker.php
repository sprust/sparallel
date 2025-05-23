<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Fork;

use Closure;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\ForkStarterInterface;
use SParallel\Exceptions\CouldNotForkProcessException;
use SParallel\Services\Context;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\TaskResultTransport;

readonly class Forker
{
    public function __construct(
        protected TaskResultTransport     $resultTransport,
        protected SocketService           $socketService,
        protected CallbackCallerInterface $callbackCaller,
        protected EventsBusInterface      $eventsBus,
        protected ForkStarterInterface    $forkStarter,
    ) {
    }

    /**
     * Forks the current process, executes the given callback and return child process id.
     */
    public function fork(
        Context $context,
        string $driverName,
        string $socketPath,
        int|string $taskKey,
        Closure $callback
    ): int {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new CouldNotForkProcessException($taskKey);
        }

        if ($pid !== 0) {
            return $pid;
        }

        $this->forkStarter->start(
            context: $context,
            driverName: $driverName,
            socketPath: $socketPath,
            taskKey: $taskKey,
            callback: $callback
        );

        return 0;
    }
}
