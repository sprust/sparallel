<?php

declare(strict_types=1);

namespace SParallel\Drivers\Hybrid;

use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\HybridProcessCommandResolverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Exceptions\ProcessIsNotRunningException;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
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
        protected HybridProcessCommandResolverInterface $hybridProcessCommandResolver,
        protected SocketService $socketService,
        protected ProcessService $processService,
    ) {
        $this->command = $this->hybridProcessCommandResolver->get();
    }

    public function run(array &$callbacks, Context $context, int $workersLimit): WaitGroupInterface
    {
        $serializedCallbacks = [];

        $callbackKeys = array_keys($callbacks);

        foreach ($callbackKeys as $callbackKey) {
            $serializedCallbacks[$callbackKey] = $this->callbackTransport->serialize($callbacks[$callbackKey]);

            unset($callbacks[$callbackKey]);
        }

        $serializedContext = $this->contextTransport->serialize($context);

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
                $context->check();

                usleep(1000);

                continue;
            }

            $data = json_encode([
                'ctx' => $serializedContext,
                'cbs' => $serializedCallbacks,
            ]);

            $this->socketService->writeToSocket(
                context: $context,
                socket: $processClient,
                data: $data
            );

            break;
        }

        return new HybridWaitGroup(
            taskKeys: $callbackKeys,
            process: $process,
            socketServer: $socketServer,
            context: $context,
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
