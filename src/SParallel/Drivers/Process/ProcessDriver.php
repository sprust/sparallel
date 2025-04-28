<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use SParallel\Contracts\ContextResolverInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\ProcessCommandResolverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Objects\ProcessTask;
use SParallel\Services\Canceler;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\CancelerTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ProcessMessagesTransport;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\Process;

class ProcessDriver implements DriverInterface
{
    public const DRIVER_NAME = 'process';

    public const PARAM_TASK_KEY    = 'SPARALLEL_TASK_KEY';
    public const PARAM_SOCKET_PATH = 'SPARALLEL_SOCKET_PATH';

    protected string $command;

    public function __construct(
        protected CallbackTransport $callbackTransport,
        protected CancelerTransport $cancelerTransport,
        protected ResultTransport $resultTransport,
        protected ContextTransport $contextTransport,
        protected SocketService $socketService,
        protected ProcessCommandResolverInterface $processCommandResolver,
        protected EventsBusInterface $eventsBus,
        protected ProcessMessagesTransport $messageTransport,
        protected ProcessService $processService,
        protected ContextResolverInterface $contextResolver,
    ) {
        $this->command = $this->processCommandResolver->get();
    }

    public function run(array &$callbacks, Canceler $canceler, int $workersLimit): WaitGroupInterface
    {
        $taskKeys = array_keys($callbacks);

        $socketPath = $this->socketService->makeSocketPath();

        $socketServer = $this->socketService->createServer($socketPath);

        /** @var array<mixed, ProcessTask> $processTasks */
        $processTasks = [];

        foreach ($taskKeys as $taskKey) {
            $callback = $callbacks[$taskKey];

            $process = Process::fromShellCommandline(command: $this->command)
                ->setTimeout(null)
                ->setEnv([
                    static::PARAM_TASK_KEY    => $taskKey,
                    static::PARAM_SOCKET_PATH => $socketPath,
                ]);

            $process->start();

            $processTasks[$taskKey] = new ProcessTask(
                taskKey: $taskKey,
                serializedCallback: $this->callbackTransport->serialize($callback),
                process: $process
            );

            unset($callbacks[$taskKey]);
        }

        return new ProcessWaitGroup(
            socketServer: $socketServer,
            processTasks: $processTasks,
            canceler: $canceler,
            contextResolver: $this->contextResolver,
            socketService: $this->socketService,
            contextTransport: $this->contextTransport,
            callbackTransport: $this->callbackTransport,
            cancelerTransport: $this->cancelerTransport,
            resultTransport: $this->resultTransport,
            eventsBus: $this->eventsBus,
            messageTransport: $this->messageTransport,
            processService: $this->processService
        );
    }
}
