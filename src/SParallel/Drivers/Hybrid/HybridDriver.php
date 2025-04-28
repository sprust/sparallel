<?php

declare(strict_types=1);

namespace SParallel\Drivers\Hybrid;

use SParallel\Contracts\ContextResolverInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\HybridProcessCommandResolverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Exceptions\ProcessIsNotRunningException;
use SParallel\Services\Canceler;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\CancelerTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\Process;

/**
 * One process and its forks
 */
class HybridDriver implements DriverInterface
{
    public const DRIVER_NAME = 'hybrid';

    public const PARAM_SOCKET_PATH   = 'SPARALLEL_SOCKET_PATH';
    public const PARAM_WORKERS_LIMIT = 'SPARALLEL_WORKERS_LIMIT';

    protected string $command;

    public function __construct(
        protected EventsBusInterface $eventsBus,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected ContextTransport $contextTransport,
        protected CancelerTransport $cancelerTransport,
        protected HybridProcessCommandResolverInterface $hybridProcessCommandResolver,
        protected SocketService $socketService,
        protected ProcessService $processService,
        protected ContextResolverInterface $contextResolver,
    ) {
        $this->command = $this->hybridProcessCommandResolver->get();
    }

    public function run(array &$callbacks, Canceler $canceler, int $workersLimit): WaitGroupInterface
    {
        $serializedCallbacks = [];

        $callbackKeys = array_keys($callbacks);

        foreach ($callbackKeys as $callbackKey) {
            $serializedCallbacks[$callbackKey] = $this->callbackTransport->serialize($callbacks[$callbackKey]);

            unset($callbacks[$callbackKey]);
        }

        $serializedContext  = $this->contextTransport->serialize($this->contextResolver->get());
        $serializedCanceler = $this->cancelerTransport->serialize($canceler);

        $socketPath = $this->socketService->makeSocketPath();

        $socketServer = $this->socketService->createServer($socketPath);

        $process = Process::fromShellCommandline(command: $this->command)
            ->setTimeout(null)
            ->setEnv([
                static::PARAM_SOCKET_PATH   => $socketPath,
                static::PARAM_WORKERS_LIMIT => $workersLimit,
            ]);

        $process->start();

        // wait for the main process to start and to put payload
        while ($this->checkProcess($process)) {
            $processClient = @socket_accept($socketServer->socket);

            if ($processClient === false) {
                $canceler->check();

                usleep(1000);

                continue;
            }

            $data = json_encode([
                'ctx' => $serializedContext,
                'can' => $serializedCanceler,
                'cbs' => $serializedCallbacks,
            ]);

            $this->socketService->writeToSocket(
                canceler: $canceler,
                socket: $processClient,
                data: $data
            );

            break;
        }

        return new HybridWaitGroup(
            taskKeys: $callbackKeys,
            process: $process,
            socketServer: $socketServer,
            canceler: $canceler,
            eventsBus: $this->eventsBus,
            socketService: $this->socketService,
            resultTransport: $this->resultTransport,
        );
    }

    protected function checkProcess(Process $process): bool
    {
        if ($process->isRunning()) {
            return true;
        }

        throw new ProcessIsNotRunningException(
            pid: $process->getPid(),
            description: $this->processService->getOutput($process)
        );
    }
}
