<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Hybrid;

use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\FlowInterface;
use SParallel\Entities\Timer;
use SParallel\Enum\MessageOperationTypeEnum;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\InvalidValueException;
use SParallel\Exceptions\UnexpectedTaskException;
use SParallel\Exceptions\UnexpectedTaskOperationException;
use SParallel\Flows\ASync\Fork\Forker;
use SParallel\Flows\ASync\Fork\ForkService;
use SParallel\Flows\ASync\Fork\ForkTaskManager;
use SParallel\Flows\FlowFactory;
use SParallel\Objects\Message;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\MessageTransport;
use SParallel\Transport\ResultTransport;

class HybridProcessHandler
{
    /**
     * @var array<int|string, int> $activeTaskPids
     */
    protected array $activeTaskPids;

    public function __construct(
        protected ContextTransport $contextTransport,
        protected EventsBusInterface $eventsBus,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected SocketService $socketService,
        protected Forker $forkExecutor,
        protected ForkService $forkService,
        protected FlowFactory $flowFactory,
        protected ForkTaskManager $forkTaskManager,
        protected ProcessService $processService,
        protected MessageTransport $messageTransport,
    ) {
    }

    /**
     * @throws ContextCheckerException
     */
    public function handle(): void
    {
        $this->activeTaskPids = [];

        $myPid = getmypid();

        $this->eventsBus->processCreated($myPid);

        $exitHandler = function () use ($myPid) {
            foreach ($this->activeTaskPids as $activeTaskPid) {
                $this->forkService->finish($activeTaskPid);
            }

            $this->eventsBus->processFinished(pid: $myPid);

            exit(0);
        };

        $this->processService->registerShutdownFunction($exitHandler);
        $this->processService->registerExitSignals($exitHandler);

        $managerSocketPath = $_SERVER[HybridTaskManager::PARAM_MANAGER_SOCKET_PATH] ?? null;

        if (!$managerSocketPath || !is_string($managerSocketPath)) {
            throw new InvalidValueException(
                'Parent socket path is not set or is not a string.'
            );
        }

        $flowSocketPath = $_SERVER[HybridTaskManager::PARAM_FLOW_SOCKET_PATH] ?? null;

        if (!$flowSocketPath || !is_string($flowSocketPath)) {
            throw new InvalidValueException(
                'Flow socket path is not set or is not a string.'
            );
        }

        $client = $this->socketService->createClient($managerSocketPath);

        $initContext = new Context();

        $response = $this->socketService->readSocket(
            context: $initContext->setChecker(new Timer(timeoutSeconds: 2)),
            socket: $client->socket
        );

        $responseData = json_decode($response, true);

        $context = $this->contextTransport->unserialize($responseData['ctx']);

        $callbacks = array_map(
            fn(string $serializedCallbacks) => $this->callbackTransport->unserialize($serializedCallbacks),
            $responseData['cbs']
        );

        $socketServer = $this->socketService->createServer(
            $this->socketService->makeSocketPath()
        );

        $client = $this->socketService->createClient($managerSocketPath);

        $this->socketService->writeToSocket(
            context: $initContext->setChecker(new Timer(timeoutSeconds: 2)),
            socket: $client->socket,
            data: $socketServer->path
        );

        while (count($callbacks) > 0 || count($this->activeTaskPids) > 0) {
            $activeTaskKeys = array_keys($this->activeTaskPids);

            foreach ($activeTaskKeys as $activeTaskKey) {
                $taskPid = $this->activeTaskPids[$activeTaskKey];

                if (!$this->forkService->isFinished($taskPid)) {
                    continue;
                }

                $this->forkService->finish($taskPid);

                unset($this->activeTaskPids[$activeTaskKey]);

                $client = $this->socketService->createClient($managerSocketPath);

                $this->socketService->writeToSocket(
                    context: $context,
                    socket: $client->socket,
                    data: $this->messageTransport->serialize(
                        new Message(
                            operation: MessageOperationTypeEnum::TaskFinished,
                            taskKey: $activeTaskKey,
                        )
                    )
                );
            }

            $client = $this->socketService->accept(
                socket: $socketServer->socket
            );

            if ($client === false) {
                $context->check();

                usleep(100);

                continue;
            }

            $response = $this->socketService->readSocket(
                context: $context,
                socket: $client
            );

            $message = $this->messageTransport->unserialize($response);

            $taskKey = $message->taskKey;

            if ($message->operation === MessageOperationTypeEnum::TaskStart) {
                if (!array_key_exists($taskKey, $callbacks)) {
                    throw new UnexpectedTaskException(
                        unexpectedTaskKey: $taskKey
                    );
                }

                $taskPid = $this->forkExecutor->fork(
                    context: $context,
                    driverName: HybridTaskManager::DRIVER_NAME,
                    socketPath: $flowSocketPath,
                    taskKey: $taskKey,
                    callback: $callbacks[$taskKey],
                );

                $this->activeTaskPids[$taskKey] = $taskPid;

                unset($callbacks[$taskKey]);
            } else {
                throw new UnexpectedTaskOperationException(
                    taskKey: $taskKey,
                    operation: $message->operation->value,
                );
            }
        }
    }
}
