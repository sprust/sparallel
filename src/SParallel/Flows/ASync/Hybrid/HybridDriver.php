<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Hybrid;

use Closure;
use Psr\Log\LoggerInterface;
use SParallel\Contracts\HybridProcessCommandResolverInterface;
use SParallel\Contracts\TaskInterface;
use SParallel\Contracts\DriverInterface;
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
use Throwable;

class HybridDriver implements DriverInterface
{
    public const DRIVER_NAME = 'hybrid';

    public const PARAM_DRIVER_SOCKET_PATH = 'SPARALLEL_DRIVER_SOCKET_PATH';
    public const PARAM_FLOW_SOCKET_PATH   = 'SPARALLEL_FLOW_SOCKET_PATH';

    protected Process $handler;

    /**
     * @var array<int|string>
     */
    protected array $finishedTaskKeys;

    protected SocketServer $socketServer;
    protected SocketServer $handlerSocketServer;

    public function __construct(
        protected HybridProcessCommandResolverInterface $processCommandResolver,
        protected ProcessService $processService,
        protected CallbackTransport $callbackTransport,
        protected ContextTransport $contextTransport,
        protected SocketService $socketService,
        protected MessageTransport $messageTransport,
        protected LoggerInterface $logger,
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

        $this->handler = Process::fromShellCommandline(command: $this->processCommandResolver->get())
            ->setTimeout(null)
            ->setEnv([
                static::PARAM_DRIVER_SOCKET_PATH => $socketPath,
                static::PARAM_FLOW_SOCKET_PATH   => $socketServer->path,
            ]);

        $this->handler->start();

        $this->logger->debug(
            sprintf(
                "hybrid driver starts handler [hPid: %s]",
                $this->handler->getPid()
            )
        );

        // wait for the main process to start and to put payload
        while (true) {
            $this->checkProcess();

            $client = $this->socketService->accept($this->socketServer->socket);

            if ($client === false) {
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
                socket: $client,
                data: $data
            );

            $this->logger->debug(
                sprintf(
                    'hybrid driver sent payload to handler [hPid: %s]',
                    $this->handler->getPid()
                )
            );

            unset($client);

            break;
        }

        // get socket server for task messages
        while (true) {
            $this->checkProcess();

            $client = $this->socketService->accept($this->socketServer->socket);

            if ($client === false) {
                $context->check();

                usleep(100);

                continue;
            }

            $response = $this->socketService->readSocket(
                context: $context,
                socket: $client,
            );

            unset($client);

            $this->handlerSocketServer = $this->socketService->createServer(
                socketPath: $response
            );

            $this->logger->debug(
                sprintf(
                    'hybrid driver connected to process socket server [hPid: %s]',
                    $this->handler->getPid()
                )
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
        $client = $this->socketService->createClient($this->handlerSocketServer->path);

        $this->socketService->writeToSocket(
            context: $context,
            socket: $client->socket,
            data: $this->messageTransport->serialize(
                new Message(
                    operation: MessageOperationTypeEnum::StartTask,
                    taskKey: $key,
                )
            )
        );

        $this->logger->debug(
            sprintf(
                "hybrid driver sent task to process [hPid: %s, tKey: %s]",
                $this->handler->getPid(),
                $key
            )
        );

        return new HybridTask(
            context: $context,
            pid: mt_rand(),
            taskKey: $key,
            callback: $callback,
            process: $this->handler,
            processService: $this->processService,
            hybridDriver: $this
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

            unset($client);

            $message = $this->messageTransport->unserialize($response);

            if ($message->operation === MessageOperationTypeEnum::TaskFinished) {
                $this->finishedTaskKeys[] = $message->taskKey;
            } else {
                throw new UnexpectedTaskOperationException(
                    taskKey: $taskKey,
                    operation: $message->operation->value,
                );
            }

            $this->logger->debug(
                sprintf(
                    "hybrid driver got task finished signal [hPid: %s, tKey: %s]",
                    $this->handler->getPid(),
                    $message->taskKey,
                )
            );
        }

        return in_array($taskKey, $this->finishedTaskKeys);
    }

    private function checkProcess(): void
    {
        if ($this->handler->isRunning()) {
            return;
        }

        throw new ProcessIsNotRunningException(
            pid: $this->handler->getPid(),
            description: $this->processService->getOutput($this->handler)
        );
    }

    public function __destruct()
    {
        if (isset($this->handler) && $this->handler->isRunning()) {
            try {
                $this->handler->stop();
            } catch (Throwable) {
                //
            }
        }

        if (isset($this->socketServer)) {
            unset($this->socketServer);
        }

        if (isset($this->handlerSocketServer)) {
            unset($this->handlerSocketServer);
        }
    }
}
