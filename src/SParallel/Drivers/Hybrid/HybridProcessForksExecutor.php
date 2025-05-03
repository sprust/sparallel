<?php

declare(strict_types=1);

namespace SParallel\Drivers\Hybrid;

use SParallel\Exceptions\ContextCheckerException;
use SParallel\Services\Context;
use SParallel\Services\Fork\Forker;
use SParallel\Services\Fork\ForkService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ResultTransport;

class HybridProcessForksExecutor
{
    /**
     * @var array<mixed, int>
     */
    protected array $activeProcessIds;

    /**
     * @var array<mixed>
     */
    protected array $remainTaskKeys;

    /**
     * @param array<mixed, string> $serializedCallbacks
     */
    public function __construct(
        protected array &$serializedCallbacks,
        protected int $workersLimit,
        protected string $socketPath,
        protected Context $context,
        protected Forker $forker,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected SocketService $socketService,
        protected ForkService $forkService,
    ) {
        $this->activeProcessIds = [];

        $this->remainTaskKeys = array_keys($this->serializedCallbacks);
    }

    /**
     * @throws ContextCheckerException
     */
    public function exec(): void
    {
        $context = $this->context;

        while (true) {
            $context->check();

            $activeProcessIdKeys = array_keys($this->activeProcessIds);

            foreach ($activeProcessIdKeys as $activeProcessIdKey) {
                $childProcessPid = $this->activeProcessIds[$activeProcessIdKey];

                if ($this->forkService->isFinished($childProcessPid)) {
                    unset($this->activeProcessIds[$activeProcessIdKey]);
                }
            }

            $this->shiftWorkers();

            if (count($this->activeProcessIds) === 0) {
                break;
            }

            usleep(1000);
        }
    }

    protected function shiftWorkers(): void
    {
        $activeProcessIdsCount = count($this->activeProcessIds);

        if ($activeProcessIdsCount >= $this->workersLimit) {
            return;
        }

        $taskKeys = array_slice(
            array: array_keys($this->serializedCallbacks),
            offset: 0,
            length: $this->workersLimit - $activeProcessIdsCount
        );

        foreach ($taskKeys as $taskKey) {
            $serializedCallback = $this->serializedCallbacks[$taskKey];

            $this->activeProcessIds[$taskKey] = $this->forker->fork(
                context: $this->context,
                driverName: HybridDriver::DRIVER_NAME,
                socketPath: $this->socketPath,
                taskKey: $taskKey,
                callback: $this->callbackTransport->unserialize($serializedCallback),
            );

            unset($this->serializedCallbacks[$taskKey]);
        }
    }

    public function __destruct()
    {
        $taskKeys = array_keys($this->activeProcessIds);

        foreach ($taskKeys as $taskKey) {
            $pid = $this->activeProcessIds[$taskKey];

            if (!$this->forkService->isFinished($pid)) {
                posix_kill($pid, SIGKILL);
            }

            unset($this->activeProcessIds[$taskKey]);
        }
    }
}
