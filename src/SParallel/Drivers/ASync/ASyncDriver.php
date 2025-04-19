<?php

declare(strict_types=1);

namespace SParallel\Drivers\ASync;

use Generator;
use RuntimeException;
use Socket;
use SParallel\Contracts\ASyncScriptPathResolverInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\ProcessConnectionInterface;
use SParallel\Drivers\Timer;
use SParallel\Objects\Context;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ASyncDriver implements DriverInterface
{
    public const PARAM_SOCKET_PATH = 'SPARALLEL_SOCKET_PATH';
    public const DRIVER_NAME = 'async';

    protected string $scriptPath;

    public function __construct(
        protected ProcessConnectionInterface $connection,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected ContextTransport $contextTransport,
        protected ASyncScriptPathResolverInterface $processScriptPathResolver,
        protected SocketIO $socketIO,
        protected ?Context $context = null,
    ) {
        $this->scriptPath = $this->processScriptPathResolver->get();
    }

    public function run(array &$callbacks, Timer $timer): Generator
    {
        $serializedCallbacks = [];

        $callbackKeys = array_keys($callbacks);

        /** @var array<mixed, Socket> $childrenSockets */
        $childrenSockets = [];

        foreach ($callbackKeys as $callbackKey) {
            $callback = $callbacks[$callbackKey];

            $childrenSocketPath = $this->socketIO->makeSocketPath();

            $serializedCallbacks[$callbackKey] = [
                'sp' => $childrenSocketPath,
                'cb' => $this->callbackTransport->serialize($callback),
            ];

            $childrenSockets[$callbackKey] = $this->socketIO->createServer($childrenSocketPath);

            unset($callbacks[$callbackKey]);
        }

        $mainSocketPath = $this->socketIO->makeSocketPath();

        $mainSocket = $this->socketIO->createServer($mainSocketPath);

        $serializedContext = $this->contextTransport->serialize($this->context);

        $command = sprintf(
            '%s %s',
            (new PhpExecutableFinder())->find(),
            $this->scriptPath,
        );

        $mainProcess = Process::fromShellCommandline(command: $command)
            ->setTimeout(null)
            ->setEnv([
                static::PARAM_SOCKET_PATH => $mainSocketPath,
            ]);

        $mainProcess->start();

        // wait for the main process to start
        while ($this->checkMainProcess($mainProcess)) {
            $mainClient = @socket_accept($mainSocket);

            if ($mainClient === false) {
                usleep(1000);

                $timer->check();

                continue;
            }

            $data = json_encode([
                'sc' => $serializedContext,
                'pl' => $serializedCallbacks,
            ]);

            try {
                $this->socketIO->writeToSocket(
                    socket: $mainClient,
                    data: $data
                );
            } finally {
                socket_close($mainClient);
            }

            break;
        }

        while (count($childrenSockets) > 0) {
            $callbackKeys = array_keys($childrenSockets);

            foreach ($callbackKeys as $callbackKey) {
                $childrenSocket = $childrenSockets[$callbackKey];

                $childClient = @socket_accept($childrenSocket);

                if ($childClient === false) {
                    usleep(1000);

                    $timer->check();

                    continue;
                }

                $response = $this->socketIO->readSocket($childClient);

                unset($childrenSockets[$callbackKey]);

                yield $this->resultTransport->unserialize($response);
            }
        }
    }

    protected function checkMainProcess(Process $process): bool
    {
        if ($process->isRunning()) {
            return true;
        }

        throw new RuntimeException(
            message: sprintf(
                'Main process[%s] is not running:\n%s',
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
