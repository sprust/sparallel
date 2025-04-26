<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\ProcessScriptPathResolverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Exceptions\ProcessScriptNotExistsException;
use SParallel\Objects\ProcessTask;
use SParallel\Services\Canceler;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\CancelerTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ProcessMessagesTransport;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ProcessDriver implements DriverInterface
{
    public const DRIVER_NAME = 'process';

    public const PARAM_TASK_KEY    = 'SPARALLEL_TASK_KEY';
    public const PARAM_SOCKET_PATH = 'SPARALLEL_SOCKET_PATH';

    protected string $scriptPath;

    public function __construct(
        protected CallbackTransport $callbackTransport,
        protected CancelerTransport $cancelerTransport,
        protected ResultTransport $resultTransport,
        protected ContextTransport $contextTransport,
        protected SocketService $socketService,
        protected ProcessScriptPathResolverInterface $processScriptPathResolver,
        protected EventsBusInterface $eventsBus,
        protected ProcessMessagesTransport $messageTransport,
        protected ProcessService $processService,
        protected Context $context,
    ) {
        $this->scriptPath = $this->processScriptPathResolver->get();
    }

    public function run(array &$callbacks, Canceler $canceler): WaitGroupInterface
    {
        $this->checkScriptPath();

        $command = sprintf(
            '%s %s',
            (new PhpExecutableFinder())->find(),
            $this->scriptPath,
        );

        $taskKeys = array_keys($callbacks);

        $socketPath = $this->socketService->makeSocketPath();

        $socketServer = $this->socketService->createServer($socketPath);

        /** @var array<mixed, ProcessTask> $processTasks */
        $processTasks = [];

        foreach ($taskKeys as $taskKey) {
            $callback = $callbacks[$taskKey];

            $process = Process::fromShellCommandline(command: $command)
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
            context: $this->context,
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

    private function checkScriptPath(): void
    {
        $scriptPath = explode(' ', $this->scriptPath)[0];

        if (!file_exists($scriptPath)) {
            throw new ProcessScriptNotExistsException($scriptPath);
        }
    }
}
