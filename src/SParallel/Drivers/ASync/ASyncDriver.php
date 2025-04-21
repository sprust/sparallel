<?php

declare(strict_types=1);

namespace SParallel\Drivers\ASync;

use RuntimeException;
use SParallel\Contracts\ASyncScriptPathResolverInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Timer;
use SParallel\Objects\Context;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * TODO: use SOMAXCONN const for limiting connections
 */
class ASyncDriver implements DriverInterface
{
    public const PARAM_SOCKET_PATH           = 'SPARALLEL_SOCKET_PATH';
    public const PARAM_TIMER_TIMEOUT_SECONDS = 'SPARALLEL_TIMER_TIMEOUT_SECONDS';
    public const PARAM_TIMER_START_TIME      = 'SPARALLEL_TIMER_START_TIME';

    public const DRIVER_NAME = 'async';

    protected string $scriptPath;

    public function __construct(
        protected EventsBusInterface $eventsBus,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected ContextTransport $contextTransport,
        protected ASyncScriptPathResolverInterface $processScriptPathResolver,
        protected SocketService $socketService,
        protected ?Context $context = null,
    ) {
        $this->scriptPath = $this->processScriptPathResolver->get();
    }

    public function run(array &$callbacks, Timer $timer): WaitGroupInterface
    {
        $serializedCallbacks = [];

        $callbackKeys = array_keys($callbacks);

        foreach ($callbackKeys as $callbackKey) {
            $serializedCallbacks[$callbackKey] = $this->callbackTransport->serialize($callbacks[$callbackKey]);

            unset($callbacks[$callbackKey]);
        }

        $serializedContext = $this->contextTransport->serialize($this->context);

        $command = sprintf(
            '%s %s',
            (new PhpExecutableFinder())->find(),
            $this->scriptPath,
        );

        $socketPath = $this->socketService->makeSocketPath();

        $processSocketServer = $this->socketService->createServer($socketPath);

        $process = Process::fromShellCommandline(command: $command)
            ->setTimeout(null)
            ->setEnv([
                static::PARAM_SOCKET_PATH           => $socketPath,
                static::PARAM_TIMER_TIMEOUT_SECONDS => $timer->timeoutSeconds,
                static::PARAM_TIMER_START_TIME      => $timer->startTime,
            ]);

        $process->start();

        // wait for the main process to start and to put payload
        while ($this->checkProcess($process)) {
            $processClient = @socket_accept($processSocketServer->socket);

            if ($processClient === false) {
                $timer->check();

                usleep(1000);

                continue;
            }

            $data = json_encode([
                'c'  => $serializedContext,
                'cb' => $serializedCallbacks,
            ]);

            $this->socketService->writeToSocket(
                timer: $timer,
                socket: $processClient,
                data: $data
            );

            $this->socketService->closeSocket($processClient);

            break;
        }

        return new AsyncWaitGroup(
            taskKeys: $callbackKeys,
            process: $process,
            processSocketServer: $processSocketServer,
            timer: $timer,
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

        throw new RuntimeException(
            message: sprintf(
                'Process[%s] is not running:\n%s',
                $process->getPid(),
                $this->readProcessOutput($process) ?: 'No output available.'
            )
        );
    }

    protected function readProcessOutput(Process $process): ?string
    {
        if (!$process->isStarted()) {
            return null;
        }

        if ($output = $process->getOutput()) {
            $process->clearOutput();

            return $output;
        }

        if ($errorOutput = $process->getErrorOutput()) {
            $process->clearErrorOutput();

            return $errorOutput;
        }

        return null;
    }
}
