<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Hybrid;

use SParallel\Contracts\HybridProcessCommandResolverInterface;
use SParallel\Contracts\TaskInterface;
use SParallel\Contracts\TaskManagerInterface;
use SParallel\Entities\SocketServer;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\ProcessIsNotRunningException;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use Symfony\Component\Process\Process;

class HybridTaskManager implements TaskManagerInterface
{
    public const DRIVER_NAME = 'hybrid';

    public const PARAM_PARENT_SOCKET_PATH = 'PARAM_PARENT_SOCKET_PATH';
    public const PARAM_FLOW_SOCKET_PATH   = 'PARAM_FLOW_SOCKET_PATH';
    public const PARAM_WORKERS_LIMIT      = 'SPARALLEL_WORKERS_LIMIT';

    protected ?Process $process;

    /**
     * @var array<int|string>
     */
    protected array $finishedTaskKeys;

    protected SocketServer $socketServer;

    public function __construct(
        protected HybridProcessCommandResolverInterface $processCommandResolver,
        protected ProcessService $processService,
        protected CallbackTransport $callbackTransport,
        protected ContextTransport $contextTransport,
        protected SocketService $socketService,
    ) {
    }

    /**
     * @throws ContextCheckerException
     */
    public function init(
        Context $context,
        array &$callbacks,
        int $workersLimit,
        SocketServer $socketServer
    ): void {
        $this->finishedTaskKeys = [];

        $serializedCallbacks = [];

        $callbackKeys = array_keys($callbacks);

        foreach ($callbackKeys as $callbackKey) {
            $serializedCallbacks[$callbackKey] = $this->callbackTransport->serialize($callbacks[$callbackKey]);
        }

        $serializedContext = $this->contextTransport->serialize($context);

        $socketPath = $this->socketService->makeSocketPath();

        $this->socketServer = $this->socketService->createServer($socketPath);

        $this->process = Process::fromShellCommandline(command: $this->processCommandResolver->get())
            ->setTimeout(null)
            ->setEnv([
                static::PARAM_PARENT_SOCKET_PATH => $socketPath,
                static::PARAM_FLOW_SOCKET_PATH   => $socketServer->path,
                static::PARAM_WORKERS_LIMIT      => $workersLimit,
            ]);

        $this->process->start();

        // wait for the main process to start and to put payload
        while (true) {
            if (!$this->process->isRunning()) {
                throw new ProcessIsNotRunningException(
                    pid: $this->process->getPid(),
                    description: $this->processService->getOutput($this->process)
                );
            }

            $processClient = $this->socketService->accept($this->socketServer->socket);

            if ($processClient === false) {
                $context->check();

                usleep(100);

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
    }

    public function create(
        Context $context,
        SocketServer $socketServer,
        int|string $key,
        callable $callback
    ): TaskInterface {
        return new HybridTask(
            context: $context,
            pid: mt_rand(),
            taskKey: $key,
            callback: $callback,
            process: $this->process,
            processService: $this->processService,
            hybridTaskManager: $this
        );
    }

    /**
     * @throws ContextCheckerException
     */
    public function isTaskFinished(Context $context, int|string $taskKey): bool
    {
        while (true) {
            $client = $this->socketService->accept($this->socketServer->socket);

            if ($client === false) {
                break;
            }

            $response = $this->socketService->readSocket(
                context: $context,
                socket: $client
            );

            $this->finishedTaskKeys[] = unserialize($response);
        }

        return in_array($taskKey, $this->finishedTaskKeys);
    }
}
