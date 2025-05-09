<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Hybrid;

use Closure;
use SParallel\Contracts\HybridProcessCommandResolverInterface;
use SParallel\Contracts\TaskInterface;
use SParallel\Contracts\TaskManagerInterface;
use SParallel\Entities\SocketServer;
use SParallel\Enum\MessageOperationTypeEnum;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\ProcessIsNotRunningException;
use SParallel\Exceptions\UnexpectedTaskOperationException;
use SParallel\Objects\Message;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\MessageTransport;
use Symfony\Component\Process\Process;

class HybridTaskManager implements TaskManagerInterface
{
    public const DRIVER_NAME = 'hybrid';

    public const PARAM_MANAGER_SOCKET_PATH = 'PARAM_PARENT_SOCKET_PATH';
    public const PARAM_FLOW_SOCKET_PATH    = 'PARAM_FLOW_SOCKET_PATH';

    protected ?Process $process;

    /**
     * @var array<int|string>
     */
    protected array $finishedTaskKeys;

    protected SocketServer $socketServer;
    protected SocketServer $processSocketServer;

    public function __construct(
        protected HybridProcessCommandResolverInterface $processCommandResolver,
        protected ProcessService $processService,
        protected CallbackTransport $callbackTransport,
        protected ContextTransport $contextTransport,
        protected SocketService $socketService,
        protected MessageTransport $messageTransport
    ) {
    }

    /**
     * @throws ContextCheckerException
     */
    public function init(
        Context $context,
        array $callbacks,
        int $workersLimit,
        SocketServer $socketServer
    ): void {
        $this->finishedTaskKeys = [];

        $serializedCallbacks = [];

        $taskKeys = array_keys($callbacks);

        foreach ($taskKeys as $callbackKey) {
            $serializedCallbacks[$callbackKey] = $this->callbackTransport->serialize(
                $callbacks[$callbackKey]
            );
        }

        $serializedContext = $this->contextTransport->serialize($context);

        $socketPath = $this->socketService->makeSocketPath();

        $this->socketServer = $this->socketService->createServer($socketPath);

        $this->process = Process::fromShellCommandline(command: $this->processCommandResolver->get())
            ->setTimeout(null)
            ->setEnv([
                static::PARAM_MANAGER_SOCKET_PATH => $socketPath,
                static::PARAM_FLOW_SOCKET_PATH    => $socketServer->path,
            ]);

        $this->process->start();

        // wait for the main process to start and to put payload
        while (true) {
            $this->checkProcess();

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

        // get socket server for task messages
        while (true) {
            $this->checkProcess();

            $processClient = $this->socketService->accept($this->socketServer->socket);

            if ($processClient === false) {
                $context->check();

                usleep(100);

                continue;
            }

            $response = $this->socketService->readSocket(
                context: $context,
                socket: $processClient,
            );

            $this->processSocketServer = $this->socketService->createServer(
                socketPath: $response
            );

            break;
        }
    }

    /**
     * @throws ContextCheckerException
     */
    public function create(
        Context $context,
        SocketServer $socketServer,
        int|string $key,
        Closure $callback
    ): TaskInterface {
        $client = $this->socketService->createClient($this->processSocketServer->path);

        $this->socketService->writeToSocket(
            context: $context,
            socket: $client->socket,
            data: $this->messageTransport->serialize(
                new Message(
                    operation: MessageOperationTypeEnum::TaskStart,
                    taskKey: $key,
                )
            )
        );

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

            $message = $this->messageTransport->unserialize($response);

            if ($message->operation === MessageOperationTypeEnum::TaskFinished) {
                $this->finishedTaskKeys[] = $message->taskKey;
            } else {
                throw new UnexpectedTaskOperationException(
                    taskKey: $taskKey,
                    operation: $message->operation->value,
                );
            }
        }

        return in_array($taskKey, $this->finishedTaskKeys);
    }

    private function checkProcess(): void
    {
        if ($this->process->isRunning()) {
            return;
        }

        throw new ProcessIsNotRunningException(
            pid: $this->process->getPid(),
            description: $this->processService->getOutput($this->process)
        );
    }
}
