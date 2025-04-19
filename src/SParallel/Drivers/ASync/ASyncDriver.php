<?php

declare(strict_types=1);

namespace SParallel\Drivers\ASync;

use Generator;
use RuntimeException;
use SParallel\Contracts\ASyncScriptPathResolverInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\ProcessConnectionInterface;
use SParallel\Drivers\Timer;
use SParallel\Objects\Context;
use SParallel\Objects\ResultObject;
use SParallel\Objects\SocketServerObject;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ASyncDriver implements DriverInterface
{
    public const PARAM_SOCKET_PATH           = 'SPARALLEL_SOCKET_PATH';
    public const PARAM_TIMER_TIMEOUT_SECONDS = 'SPARALLEL_TIMER_TIMEOUT_SECONDS';
    public const PARAM_TIMER_START_TIME      = 'SPARALLEL_TIMER_START_TIME';

    public const DRIVER_NAME = 'async';

    protected string $scriptPath;

    public function __construct(
        protected EventsBusInterface $eventsBus,
        protected ProcessConnectionInterface $connection,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected ContextTransport $contextTransport,
        protected ASyncScriptPathResolverInterface $processScriptPathResolver,
        protected SocketService $socketService,
        protected ?Context $context = null,
    ) {
        $this->scriptPath = $this->processScriptPathResolver->get();
    }

    public function run(array &$callbacks, Timer $timer): Generator
    {
        $serializedCallbacks = [];

        $callbackKeys = array_keys($callbacks);

        /** @var array<mixed, SocketServerObject> $childSocketServers */
        $childSocketServers = [];

        foreach ($callbackKeys as $callbackKey) {
            $callback = $callbacks[$callbackKey];

            $childrenSocketPath = $this->socketService->makeSocketPath();

            $serializedCallbacks[$callbackKey] = [
                'sp' => $childrenSocketPath,
                'cb' => $this->callbackTransport->serialize($callback),
            ];

            $childSocketServers[$callbackKey] = $this->socketService->createServer($childrenSocketPath);

            unset($callbacks[$callbackKey]);
        }

        $socketPath = $this->socketService->makeSocketPath();

        $processSocketServer = $this->socketService->createServer($socketPath);

        $serializedContext = $this->contextTransport->serialize($this->context);

        $command = sprintf(
            '%s %s',
            (new PhpExecutableFinder())->find(),
            $this->scriptPath,
        );

        $process = Process::fromShellCommandline(command: $command)
            ->setTimeout(null)
            ->setEnv([
                static::PARAM_SOCKET_PATH           => $socketPath,
                static::PARAM_TIMER_TIMEOUT_SECONDS => $timer->timeoutSeconds,
                static::PARAM_TIMER_START_TIME      => $timer->startTime,
            ]);

        $process->start();

        $this->eventsBus->processCreated(pid: $process->getPid());

        // wait for the main process to start
        while ($this->checkProcess($process)) {
            $processClient = @socket_accept($processSocketServer->socket);

            if ($processClient === false) {
                $timer->check();

                usleep(1000);

                continue;
            }

            $data = json_encode([
                'sc' => $serializedContext,
                'pl' => $serializedCallbacks,
            ]);

            try {
                $this->socketService->writeToSocket(
                    timer: $timer,
                    socket: $processClient,
                    data: $data
                );
            } finally {
                $this->socketService->closeSocketServer($processSocketServer);
            }

            break;
        }

        while (count($childSocketServers) > 0) {
            $callbackKeys = array_keys($childSocketServers);

            foreach ($callbackKeys as $callbackKey) {
                $childSocketServer = $childSocketServers[$callbackKey];

                $childClient = @socket_accept($childSocketServer->socket);

                if ($childClient === false) {
                    if (!$process->isRunning()) {
                        $this->socketService->closeSocketServer($childSocketServer);

                        unset($childSocketServers[$callbackKey]);

                        yield new ResultObject(
                            key: $callbackKey,
                            exception: new RuntimeException(
                                message: 'Process is not running. Socket server is closed.'
                            )
                        );
                    } else {
                        $timer->check();

                        usleep(1000);
                    }
                } else {
                    try {
                        $response = $this->socketService->readSocket(
                            timer: $timer,
                            socket: $childClient
                        );
                    } finally {
                        $this->socketService->closeSocketServer($childSocketServer);
                    }

                    unset($childSocketServers[$callbackKey]);

                    yield $this->resultTransport->unserialize($response);
                }
            }
        }
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
