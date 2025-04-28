<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use Closure;
use Generator;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Exceptions\UnexpectedTaskTerminationException;
use SParallel\Objects\SocketServer;
use SParallel\Objects\TaskResult;
use SParallel\Services\Canceler;
use SParallel\Services\Fork\ForkHandler;
use SParallel\Services\Fork\ForkService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\ResultTransport;

class ForkWaitGroup implements WaitGroupInterface
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
     * @param array<mixed, Closure> $callbacks
     */
    public function __construct(
        protected array &$callbacks,
        protected int $workersLimit,
        protected SocketServer $socketServer,
        protected Canceler $canceler,
        protected ForkHandler $forkHandler,
        protected ResultTransport $resultTransport,
        protected SocketService $socketService,
        protected ForkService $forkService,
    ) {
        $this->activeProcessIds = [];
        $this->remainTaskKeys   = array_keys($this->callbacks);

        $this->shiftWorkers();
    }

    public function get(): Generator
    {
        while (true) {
            $this->canceler->check();

            $this->shiftWorkers();

            if (!count($this->activeProcessIds)) {
                break;
            }

            $taskKeys = array_keys($this->activeProcessIds);

            foreach ($taskKeys as $taskKey) {
                $childProcessPid = $this->activeProcessIds[$taskKey];

                if ($this->forkService->isFinished($childProcessPid)) {
                    unset($this->activeProcessIds[$taskKey]);
                }
            }

            while (true) {
                $childClient = @socket_accept($this->socketServer->socket);

                if ($childClient === false) {
                    $this->canceler->check();

                    usleep(1000);

                    break;
                } else {
                    $response = $this->socketService->readSocket(
                        canceler: $this->canceler,
                        socket: $childClient
                    );

                    $result = $this->resultTransport->unserialize($response);

                    unset($this->activeProcessIds[$result->taskKey]);

                    $this->remainTaskKeys = array_filter(
                        $this->remainTaskKeys,
                        static fn(mixed $taskKey) => $taskKey !== $result->taskKey
                    );

                    yield $result;
                }
            }
        }

        while (count($this->remainTaskKeys) > 0) {
            $taskKey = array_shift($this->remainTaskKeys);

            yield new TaskResult(
                taskKey: $taskKey,
                exception: new UnexpectedTaskTerminationException(
                    taskKey: $taskKey
                )
            );
        }

        $this->break();
    }

    public function break(): void
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

    protected function shiftWorkers(): void
    {
        $activeProcessIdsCount = count($this->activeProcessIds);

        if ($activeProcessIdsCount >= $this->workersLimit) {
            return;
        }

        $taskKeys = array_slice(
            array: array_keys($this->callbacks),
            offset: 0,
            length: $this->workersLimit - $activeProcessIdsCount
        );

        foreach ($taskKeys as $taskKey) {
            $callback = $this->callbacks[$taskKey];

            $this->activeProcessIds[$taskKey] = $this->forkHandler->handle(
                canceler: $this->canceler,
                driverName: ForkDriver::DRIVER_NAME,
                socketPath: $this->socketServer->path,
                taskKey: $taskKey,
                callback: $callback
            );

            unset($this->callbacks[$taskKey]);
        }
    }

    public function __destruct()
    {
        $this->break();
    }
}
