<?php

declare(strict_types=1);

namespace SParallel\Drivers\ASync;

use Generator;
use RuntimeException;
use Socket;
use Closure;
use SParallel\Contracts\ProcessConnectionInterface;
use SParallel\Contracts\ProcessScriptPathResolverInterface;
use SParallel\Exceptions\SParallelTimeoutException;
use SParallel\Objects\Context;
use SParallel\Objects\ResultObject;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class ASyncDriver
{
    public const PARAM_SOCKET_PATH = 'SPARALLEL_SOCKET_PATH';

    protected string $scriptPath;

    public function __construct(
        protected ProcessConnectionInterface $connection,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected ContextTransport $contextTransport,
        protected ProcessScriptPathResolverInterface $processScriptPathResolver,
        protected SocketIO $socketIO,
        protected ?Context $context = null,
    ) {
        $this->scriptPath = $this->processScriptPathResolver->get();
    }

    /**
     * @param array<mixed, Closure> $callbacks
     *
     * @return Generator<ResultObject>
     * @throws SParallelTimeoutException
     */
    public function run(array &$callbacks, int $waitMicroseconds = 0): Generator
    {
        $serializedCallbacks = [];

        $callbackKeys = array_keys($callbacks);

        /** @var array<mixed, Socket> $childrenSockets */
        $childrenSockets = [];

        foreach ($callbackKeys as $callbackKey) {
            $callback = $callbacks[$callbackKey];

            $childrenSocketPath = $this->makeSocketPath();

            $serializedCallbacks[$callbackKey] = [
                'sp' => $childrenSocketPath,
                'cb' => $this->callbackTransport->serialize($callback),
            ];

            $childrenSockets[$callbackKey] = $this->createServerSocket($childrenSocketPath);

            unset($callbacks[$callbackKey]);
        }

        $mainSocketPath = $this->makeSocketPath();

        $mainSocket = $this->createServerSocket($mainSocketPath);

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

        $startTime       = (float) microtime(true);
        $comparativeTime = (float) ($waitMicroseconds / 1_000_000);

        // wait for the main process to start
        while ($this->checkMainProcess($mainProcess)) {
            $mainClient = @socket_accept($mainSocket);

            if ($mainClient === false) {
                usleep(1000);

                $this->checkTimedOut(
                    mainProcess: $mainProcess,
                    startTime: $startTime,
                    comparativeTime: $comparativeTime,
                );

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

                    $this->checkTimedOut(
                        mainProcess: $mainProcess,
                        startTime: $startTime,
                        comparativeTime: $comparativeTime,
                    );

                    continue;
                }

                $response = null;

                while (!$response) {
                    $response = $this->socketIO->readSocket($childClient);

                    $this->checkTimedOut(
                        mainProcess: $mainProcess,
                        startTime: $startTime,
                        comparativeTime: $comparativeTime,
                    );
                }

                unset($childrenSockets[$callbackKey]);

                yield $this->resultTransport->unserialize($response);
            }
        }

        var_dump('******* finished *******');
    }

    protected function createServerSocket(string $socketPath): Socket
    {
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

        socket_bind($socket, $socketPath);
        socket_listen($socket);
        socket_set_nonblock($socket);

        return $socket;
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

    protected function makeSocketPath(): string
    {
        $socketPath = '/tmp/sparallel_socket_' . uniqid((string) microtime(true));

        if (file_exists($socketPath)) {
            unlink($socketPath);
        }

        return $socketPath;
    }

    /**
     * @throws SParallelTimeoutException
     */
    protected function checkTimedOut(Process $mainProcess, float $startTime, float $comparativeTime): void
    {
        if ($output = $this->readProcessOutput($mainProcess)) {
            var_dump("MAIN PROC:\n$output");
        }

        if ($comparativeTime > 0 && (microtime(true) - $startTime) > $comparativeTime) {
            if ($mainProcess->isRunning()) {
                try {
                    $mainProcess->stop();
                } catch (Throwable) {
                    //
                }
            }
            throw new SParallelTimeoutException();
        }
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
