<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\ProcessScriptPathResolverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Timer;
use SParallel\Objects\Context;
use SParallel\Objects\ProcessTask;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ProcessMessagesTransport;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ProcessDriver implements DriverInterface
{
    public const DRIVER_NAME = 'process';

    public const PARAM_TASK_KEY              = 'SPARALLEL_TASK_KEY';
    public const PARAM_SOCKET_PATH           = 'SPARALLEL_SOCKET_PATH';
    public const PARAM_TIMER_TIMEOUT_SECONDS = 'SPARALLEL_TIMER_TIMEOUT_SECONDS';
    public const PARAM_TIMER_START_TIME      = 'SPARALLEL_TIMER_START_TIME';

    protected string $scriptPath;

    public function __construct(
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected ContextTransport $contextTransport,
        protected SocketService $socketService,
        protected ProcessScriptPathResolverInterface $processScriptPathResolver,
        protected EventsBusInterface $eventsBus,
        protected ProcessMessagesTransport $messageTransport,
        protected Context $context,
    ) {
        $this->scriptPath = $this->processScriptPathResolver->get();
    }

    public function run(array &$callbacks, Timer $timer): WaitGroupInterface
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
                    static::PARAM_TASK_KEY              => $taskKey,
                    static::PARAM_SOCKET_PATH           => $socketPath,
                    static::PARAM_TIMER_TIMEOUT_SECONDS => $timer->timeoutSeconds,
                    static::PARAM_TIMER_START_TIME      => $timer->startTime,
                ]);

            $process->start();

            $processTasks[$taskKey] = new ProcessTask(
                key: $taskKey,
                serializedCallback: $this->callbackTransport->serialize($callback),
                process: $process
            );

            unset($callbacks[$taskKey]);
        }

        return new ProcessWaitGroup(
            socketServer: $socketServer,
            processTasks: $processTasks,
            timer: $timer,
            context: $this->context,
            socketService: $this->socketService,
            contextTransport: $this->contextTransport,
            callbackTransport: $this->callbackTransport,
            resultTransport: $this->resultTransport,
            eventsBus: $this->eventsBus,
            messageTransport: $this->messageTransport
        );
    }

    private function checkScriptPath(): void
    {
        $scriptPath = explode(' ', $this->scriptPath)[0];

        if (!file_exists($scriptPath)) {
            throw new RuntimeException(
                message: sprintf(
                    'Script path [%s] does not exist.',
                    $scriptPath,
                )
            );
        }
    }
}
